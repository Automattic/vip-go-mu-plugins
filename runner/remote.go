package main

import (
	"bufio"
	"context"
	"encoding/binary"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"syscall"
	"time"

	"github.com/creack/pty"
	"github.com/howeyc/fsnotify"
	"golang.org/x/crypto/ssh/terminal"
	"golang.org/x/net/websocket"
)

type WpCliProcess struct {
	Cmd           *exec.Cmd
	Tty           *os.File
	Running       bool
	LogFileName   string
	BytesLogged   int64
	BytesStreamed map[string]int64
	padlock       *sync.Mutex
}

var (
	gGUIDttys map[string]*WpCliProcess
	padlock   *sync.Mutex
	guidRegex *regexp.Regexp

	blackListed1stLevel = []string{"admin", "cli", "config", "core", "db", "dist-archive",
		"eval-file", "eval", "find", "i18n", "scaffold", "server", "package", "profile"}

	blackListed2ndLevel = map[string][]string{
		"media":    {"regenerate"},
		"theme":    {"install", "update", "delete"},
		"plugin":   {"install", "update", "delete"},
		"language": {"install", "update", "delete"},
		"vip":      {"support-user"},
	}
)

func waitForConnect() {
	gGUIDttys = make(map[string]*WpCliProcess)
	padlock = &sync.Mutex{}

	guidRegex = regexp.MustCompile("^[a-fA-F0-9\\-]+$")
	if nil == guidRegex {
		logger.Println("Failed to compile the Guid regex")
		return
	}

	listenAddr := "0.0.0.0:22122"

	if useWebsockets {
		s := &http.Server{
			Addr: listenAddr,
			ConnContext: func(ctx context.Context, c net.Conn) context.Context {
				if tcpConn, ok := c.(*net.TCPConn); ok && tcpConn != nil {
					tcpConn.SetKeepAlivePeriod(30 * time.Second)
					tcpConn.SetKeepAlive(true)
					tcpConn.SetReadBuffer(8192)
				}
				return ctx
			},
			Handler: websocket.Handler(func(wsConn *websocket.Conn) {
				logger.Printf("websocket connection from %s\n", wsConn.RemoteAddr().String())
				authConn(wsConn)
			}),
		}
		logger.Printf("Listening for websocket protocol on %q...", listenAddr)
		wsErr := s.ListenAndServe()
		logger.Printf("Websocket listener stopped: %v", wsErr)
		return
	}

	addr, err := net.ResolveTCPAddr("tcp4", listenAddr)
	if err != nil {
		logger.Printf("error resolving listen address: %s\n", err.Error())
		return
	}

	listener, err := net.ListenTCP("tcp4", addr)
	if err != nil {
		logger.Printf("error listening on %s: %s\n", addr.String(), err.Error())
		return
	}
	defer listener.Close()

	for {
		logger.Println("listening...")
		conn, err := listener.AcceptTCP()
		logger.Printf("connection from %s\n", conn.RemoteAddr().String())
		if err != nil {
			logger.Printf("error accepting connection: %s\n", err.Error())
			continue
		}
		go authConn(conn)
	}
}

func authConn(conn net.Conn) {
	var rows, cols uint16
	var offset int64
	var token, Guid, cmd string
	var read int
	var err error
	var data []byte
	buf := make([]byte, 65535)

	logger.Println("waiting for auth data")

	conn.SetReadDeadline(time.Now().Add(time.Duration(5000 * time.Millisecond.Nanoseconds())))
	bufReader := bufio.NewReader(conn)

	for {
		read, err = bufReader.Read(buf)

		if nil != err && !strings.Contains(err.Error(), "i/o timeout") {
			conn.Write([]byte("error during handshaking\n"))
			logger.Printf("error handshaking: %s\n", err.Error())
			conn.Close()
			return
		}

		if 0 != read {
			if nil == data {
				data = make([]byte, read)
				copy(data, buf[:read])
			} else {
				data = append(data, buf[:read]...)
			}
		} else if 0 == bufReader.Buffered() {
			break
		}

		conn.SetReadDeadline(time.Now().Add(time.Duration(200 * time.Millisecond.Nanoseconds())))
	}
	buf = nil

	size := len(data)
	logger.Printf("size of handshake %d\n", size)

	// This is the minimum size to determine the protocol type
	if size < len(gRemoteToken)+gGuidLength {
		conn.Write([]byte("Error negotiating handshake"))
		logger.Println("error negotiating the handshake")
		conn.Close()
		return
	}

	newlineChars := 1
	if 1 < size && 0xd == (data[size-2 : size-1])[0] {
		newlineChars = 2
	}

	// Determine if the packet structure is the new version or not
	if ';' != data[len(gRemoteToken)] {
		token, Guid, rows, cols, offset, cmd, err = authenticateProtocolHeader2(data[:size-newlineChars])
	} else {
		token, Guid, rows, cols, cmd, err = authenticateProtocolHeader1(string(data[:size-newlineChars]))
	}
	data = nil

	if nil != err {
		conn.Write([]byte(err.Error()))
		logger.Println(err.Error())
		conn.Close()
		return
	}

	if token != gRemoteToken {
		conn.Write([]byte("invalid auth handshake"))
		logger.Printf("error incorrect handshake string")
		conn.Close()
		return
	}

	logger.Println("handshake complete!")

	conn.SetReadDeadline(time.Time{})
	if tcpConn, ok := conn.(*net.TCPConn); ok && tcpConn != nil {
		tcpConn.SetKeepAlivePeriod(time.Duration(30 * time.Second.Nanoseconds()))
		tcpConn.SetKeepAlive(true)
	}

	padlock.Lock()
	wpCliProcess, found := gGUIDttys[Guid]
	padlock.Unlock()

	if found && wpCliProcess.Running {
		if "vip-go-retrieve-remote-logs" == cmd {
			conn.Write([]byte(fmt.Sprintf("Not sending the logs because the WP-CLI command with GUID %s is still running", Guid)))
			conn.Close()
			return
		}

		// Reattach to the running WP-CLi command
		attachWpCliCmdRemote(conn, wpCliProcess, Guid, uint16(rows), uint16(cols), int64(offset))
		return
	}

	// The Guid is not currently running
	wpCliCmd, err := validateAndProcessCommand(cmd)
	if nil != err {
		logger.Println(err.Error())
		conn.Write([]byte(err.Error()))
		conn.Close()
		return
	}

	if "vip-go-retrieve-remote-logs" == wpCliCmd {
		streamLogs(conn, Guid)
		return
	}

	err = runWpCliCmdRemote(conn, Guid, uint16(rows), uint16(cols), wpCliCmd)
	if nil != err {
		logger.Println(err.Error())
	}
}

func authenticateProtocolHeader1(dataString string) (string, string, uint16, uint16, string, error) {
	var token, guid string
	var rows, cols uint64
	var err error

	elems := strings.Split(dataString, ";")
	if 5 > len(elems) {
		return "", "", 0, 0, "", errors.New("error handshake format incorrect")
	}

	token = elems[0]
	if len(token) != len(gRemoteToken) {
		return "", "", 0, 0, "", errors.New(fmt.Sprintf("error incorrect handshake reply size: %d != %d\n", len(gRemoteToken), len(elems[0])))
	}

	guid = elems[1]
	if !guidRegex.Match([]byte(guid)) {
		return "", "", 0, 0, "", errors.New("error incorrect GUID format")
	}

	rows, err = strconv.ParseUint(elems[2], 10, 16)
	if nil != err {
		return "", "", 0, 0, "", errors.New(fmt.Sprintf("error incorrect console rows setting: %s\n", err.Error()))
	}

	cols, err = strconv.ParseUint(elems[3], 10, 16)
	if nil != err {
		return "", "", 0, 0, "", errors.New(fmt.Sprintf("error incorrect console columns setting: %s\n", err.Error()))
	}

	return token, guid, uint16(rows), uint16(cols), strings.Join(elems[4:], ";"), nil
}

func authenticateProtocolHeader2(data []byte) (string, string, uint16, uint16, int64, string, error) {
	var token, guid string
	var rows, cols uint64
	var offset uint64
	var err error

	if len(data) < len(gRemoteToken)+gGuidLength+4+4+8 {
		return "", "", 0, 0, 0, "", errors.New("error negotiating the v2 protocol handshake")
	}

	token = string(data[:len(gRemoteToken)])
	guid = string(data[len(gRemoteToken) : len(gRemoteToken)+gGuidLength])

	if !guidRegex.Match([]byte(guid)) {
		return "", "", 0, 0, 0, "", errors.New("error incorrect GUID format")
	}

	rows, err = strconv.ParseUint(string(data[len(gRemoteToken)+gGuidLength:len(gRemoteToken)+gGuidLength+4]), 10, 16)
	if nil != err {
		return "", "", 0, 0, 0, "", errors.New(fmt.Sprintf("error incorrect console rows setting: %s\n", err.Error()))
	}

	cols, err = strconv.ParseUint(string(data[len(gRemoteToken)+gGuidLength+4:len(gRemoteToken)+gGuidLength+4+4]), 10, 16)
	if nil != err {
		return "", "", 0, 0, 0, "", errors.New(fmt.Sprintf("error incorrect console columns setting: %s\n", err.Error()))
	}

	offset = binary.LittleEndian.Uint64(data[len(gRemoteToken)+gGuidLength+4+4 : len(gRemoteToken)+gGuidLength+4+4+8])

	return token, guid, uint16(rows), uint16(cols), int64(offset), string(data[len(gRemoteToken)+gGuidLength+4+4+8:]), nil
}

func validateAndProcessCommand(calledCmd string) (string, error) {
	if 0 == len(strings.TrimSpace(calledCmd)) {
		return "", errors.New("No WP CLI command specified")
	}

	cmdParts := strings.Fields(strings.TrimSpace(calledCmd))
	if 0 == len(cmdParts) {
		return "", errors.New("WP CLI command not sent")
	}

	for _, command := range blackListed1stLevel {
		if strings.ToLower(strings.TrimSpace(cmdParts[0])) == command {
			return "", errors.New(fmt.Sprintf("WP CLI command '%s' is not permitted\n", command))
		}
	}

	if 1 == len(cmdParts) {
		return strings.TrimSpace(cmdParts[0]), nil
	}

	for command, blacklistedMap := range blackListed2ndLevel {
		for _, subCommand := range blacklistedMap {
			if strings.ToLower(strings.TrimSpace(cmdParts[0])) == command &&
				strings.ToLower(strings.TrimSpace(cmdParts[1])) == subCommand {
				return "", errors.New(fmt.Sprintf("WP CLI command '%s %s' is not permitted\n", command, subCommand))
			}
		}
	}

	return strings.Join(cmdParts, " "), nil
}

func getCleanWpCliArgumentArray(wpCliCmdString string) ([]string, error) {
	rawArgs := strings.Fields(wpCliCmdString)
	cleanArgs := make([]string, 0)
	openQuote := false
	arg := ""

	for _, rawArg := range rawArgs {
		if idx := strings.Index(rawArg, "\""); -1 != idx {
			if idx != strings.LastIndexAny(rawArg, "\"") {
				cleanArgs = append(cleanArgs, rawArg)
			} else if openQuote {
				arg = fmt.Sprintf("%s %s", arg, rawArg)
				cleanArgs = append(cleanArgs, arg)
				arg = ""
				openQuote = false
			} else {
				arg = rawArg
				openQuote = true
			}
		} else {
			if openQuote {
				arg = fmt.Sprintf("%s %s", arg, rawArg)
			} else {
				cleanArgs = append(cleanArgs, rawArg)
			}
		}
	}

	if openQuote {
		return make([]string, 0), errors.New(fmt.Sprintf("WP CLI command is invalid: %s\n", wpCliCmdString))
	}

	// Remove quotes from the args
	for i := range cleanArgs {
		cleanArgs[i] = strings.ReplaceAll(cleanArgs[i], "\"", "")
	}

	return cleanArgs, nil
}

func processTCPConnectionData(conn net.Conn, wpcli *WpCliProcess) {
	data := make([]byte, 8192)
	var size, written int
	var err error
	if tcpConn, ok := conn.(*net.TCPConn); ok && tcpConn != nil {
		tcpConn.SetReadBuffer(8192)
	}
	for {
		size, err = conn.Read(data)

		if nil != err {
			if io.EOF == err {
				logger.Println("client connection closed")
			} else if !strings.Contains(err.Error(), "use of closed network connection") {
				logger.Printf("error reading from the client connection: %s\n", err.Error())
			}
			break
		}

		if 0 == size {
			logger.Println("ignoring data of length 0")
			continue
		}

		if 1 == size && 0x3 == data[0] {
			logger.Println("Ctrl-C received")
			wpcli.padlock.Lock()
			// If this is the only process, then we can stop the command
			if 1 == len(wpcli.BytesStreamed) {
				wpcli.Cmd.Process.Kill()
				logger.Println("terminating the WP-CLI")
			}
			wpcli.padlock.Unlock()
			break
		}

		if 4 < len(data) && "\xc2\x9b8;" == string(data[:4]) && 't' == data[size-1:][0] {
			cmdParts := strings.Split(string(data[4:size]), ";")

			rows, err := strconv.ParseUint(cmdParts[0], 10, 16)
			if nil != err {
				logger.Printf("error reading rows resize data from the WP CLI client: %s\n", err.Error())
				continue
			}
			cols, err := strconv.ParseUint(cmdParts[1][:len(cmdParts[1])-1], 10, 16)
			if nil != err {
				logger.Printf("error reading columns resize data from the WP CLI client: %s\n", err.Error())
				continue
			}

			wpcli.padlock.Lock()
			err = pty.Setsize(wpcli.Tty, &pty.Winsize{Rows: uint16(rows), Cols: uint16(cols)})
			wpcli.padlock.Unlock()

			if nil != err {
				logger.Printf("error performing window resize: %s\n", err.Error())
			} else {
				logger.Printf("set new window size: %dx%d\n", rows, cols)
			}
			continue
		}

		wpcli.padlock.Lock()
		written, err = wpcli.Tty.Write(data[:size])
		wpcli.padlock.Unlock()

		if nil != err {
			if io.EOF != err {
				logger.Printf("error writing to the WP CLI tty: %s\n", err.Error())
				break
			}
		}
		if written != size {
			logger.Println("error writing to the WP CLI tty: not enough data written")
			break
		}
	}
}

func attachWpCliCmdRemote(conn net.Conn, wpcli *WpCliProcess, Guid string, rows uint16, cols uint16, offset int64) error {
	logger.Printf("resuming %s - rows: %d, cols: %d, offset: %d\n", Guid, rows, cols, offset)

	var err error
	remoteAddress := conn.RemoteAddr().String()
	connectionActive := true

	wpcli.padlock.Lock()
	if -1 == offset || offset > wpcli.BytesLogged {
		offset = wpcli.BytesLogged
	}
	wpcli.BytesStreamed[remoteAddress] = offset

	// Only set the window size if this client is the only one connected, otherwise the
	// original window size is maintained for the other client that is connected.
	if 1 == len(wpcli.BytesStreamed) {
		err = pty.Setsize(wpcli.Tty, &pty.Winsize{Rows: uint16(rows), Cols: uint16(cols)})
		if nil != err {
			logger.Printf("error performing window resize: %s\n", err.Error())
		} else {
			logger.Printf("set new window size: %dx%d\n", rows, cols)
		}
	}
	wpcli.padlock.Unlock()

	watcher, err := fsnotify.NewWatcher()
	if err != nil {
		conn.Write([]byte("unable to reattach to the WP CLI processs"))
		conn.Close()
		return errors.New(fmt.Sprintf("error reattaching to the WP CLI process: %s\n", err.Error()))
	}

	err = watcher.Watch(wpcli.LogFileName)
	if err != nil {
		logger.Printf("error watching the logfile: %s", err.Error())
		conn.Write([]byte("unable to open the remote process log"))
		conn.Close()
		watcher.Close()
		return errors.New(fmt.Sprintf("error watching the logfile: %s\n", err.Error()))
	}

	go func() {
		var written, read int
		var buf []byte = make([]byte, 8192)

		readFile, err := os.OpenFile(wpcli.LogFileName, os.O_RDONLY, os.ModeCharDevice)
		if nil != err {
			logger.Printf("error opening the read file for the catchup stream: %s\n", err.Error())
			conn.Close()
			return
		}

		logger.Printf("Seeking %s to %d for the catchup stream", wpcli.LogFileName, offset)
		readFile.Seek(offset, 0)

	Catchup_Loop:
		for {
			if !connectionActive {
				logger.Println("client connection is closed, exiting this catchup loop")
				break Catchup_Loop
			}

			read, err = readFile.Read(buf)
			if nil != err {
				if io.EOF != err {
					logger.Printf("error reading file for the catchup stream: %s\n", err.Error())
				}
				break Catchup_Loop
			}

			if 0 == read {
				logger.Printf("error reading file for the stream: %s\n", err.Error())
				break Catchup_Loop
			}

			written, err = conn.Write(buf[:read])
			if nil != err {
				logger.Printf("error writing to client connection: %s\n", err.Error())
				readFile.Close()
				return
			}

			wpcli.padlock.Lock()
			wpcli.BytesStreamed[remoteAddress] = wpcli.BytesStreamed[remoteAddress] + int64(written)

			if wpcli.BytesStreamed[remoteAddress] == wpcli.BytesLogged {
				wpcli.padlock.Unlock()
				break Catchup_Loop
			} else {
				wpcli.padlock.Unlock()
			}
		}

		// Used to monitor when the connection is disconnected or the CLI command finishes
		ticker := time.Tick(time.Duration(500 * time.Millisecond.Nanoseconds()))

	Watcher_Loop:
		for {
			select {
			case <-ticker:
				if !connectionActive {
					logger.Println("client connection is closed, exiting this watcher loop")
					break Watcher_Loop
				}
				if !wpcli.Running && wpcli.BytesStreamed[remoteAddress] >= wpcli.BytesLogged {
					logger.Println("WP CLI command finished and all data has been written, exiting this watcher loop")
					break Watcher_Loop
				}
			case ev := <-watcher.Event:
				if ev.IsDelete() {
					break Watcher_Loop
				}
				if !ev.IsModify() {
					continue
				}

				read, err = readFile.Read(buf)
				if 0 == read {
					continue
				}

				written, err = conn.Write(buf[:read])
				if nil != err {
					logger.Printf("error writing to client connection: %s\n", err.Error())
					break Watcher_Loop
				}

				wpcli.padlock.Lock()
				wpcli.BytesStreamed[remoteAddress] += int64(written)
				wpcli.padlock.Unlock()

			case err := <-watcher.Error:
				logger.Printf("error scanning the logfile: %s", err.Error())
				break Watcher_Loop
			}
		}

		logger.Println("closing watcher and readfile")
		watcher.Close()
		readFile.Close()

		logger.Println("closing connection at the end of the file read")
		if connectionActive {
			conn.Close()
		}
	}()

	processTCPConnectionData(conn, wpcli)
	conn.Close()
	connectionActive = false

	wpcli.padlock.Lock()
	logger.Printf("cleaning out %s\n", remoteAddress)
	delete(wpcli.BytesStreamed, remoteAddress)
	if 0 == len(wpcli.BytesStreamed) {
		logger.Printf("cleaning out %s\n", Guid)
		wpcli.padlock.Unlock()
		wpcli.padlock = nil
		padlock.Lock()
		delete(gGUIDttys, Guid)
		padlock.Unlock()
	} else {
		wpcli.padlock.Unlock()
	}

	return nil
}

func runWpCliCmdRemote(conn net.Conn, Guid string, rows uint16, cols uint16, wpCliCmdString string) error {
	cmdArgs := make([]string, 0)
	cmdArgs = append(cmdArgs, strings.Fields("--path="+wpPath)...)

	cleanArgs, err := getCleanWpCliArgumentArray(wpCliCmdString)
	if nil != err {
		conn.Write([]byte("WP CLI command is invalid"))
		conn.Close()
		return errors.New(err.Error())
	}

	cmdArgs = append(cmdArgs, cleanArgs...)

	cmd := exec.Command(wpCliPath, cmdArgs...)
	cmd.Env = append(os.Environ(), "TERM=xterm-256color")

	logger.Printf("launching %s - rows: %d, cols: %d, args: %s\n", Guid, rows, cols, strings.Join(cmdArgs, " "))

	var logFileName string
	if "os.Stdout" == logDest {
		logFileName = fmt.Sprintf("/tmp/wp-cli-%s", Guid)
	} else {
		logDir := path.Dir(logDest)
		logFileName = fmt.Sprintf("%s/wp-cli-%s", logDir, Guid)
	}

	if _, err := os.Stat(logFileName); nil == err {
		logger.Printf("Removing existing GUid logfile %s", logFileName)
		os.Remove(logFileName)
	}

	logger.Printf("Creating the logfile %s", logFileName)
	logFile, err := os.OpenFile(logFileName, os.O_APPEND|os.O_WRONLY|os.O_CREATE|os.O_SYNC, 0666)
	if nil != err {
		conn.Write([]byte("unable to launch the remote WP CLI process: " + err.Error()))
		conn.Close()
		return errors.New(fmt.Sprintf("error creating the WP CLI log file: %s\n", err.Error()))
	}

	watcher, err := fsnotify.NewWatcher()
	if err != nil {
		conn.Write([]byte("unable to launch the remote WP CLI process: " + err.Error()))
		logFile.Close()
		conn.Close()
		return errors.New(fmt.Sprintf("error launching the WP CLI log file watcher: %s\n", err.Error()))
	}

	tty, err := pty.StartWithSize(cmd, &pty.Winsize{Rows: rows, Cols: cols})
	if nil != err {
		conn.Write([]byte("unable to launch the remote WP CLI process."))
		logFile.Close()
		conn.Close()
		return errors.New(fmt.Sprintf("error setting the WP CLI tty window size: %s\n", err.Error()))
	}
	defer tty.Close()

	remoteAddress := conn.RemoteAddr().String()

	padlock.Lock()
	wpcli := &WpCliProcess{}
	wpcli.Cmd = cmd
	wpcli.BytesLogged = 0
	wpcli.BytesStreamed = make(map[string]int64)
	wpcli.BytesStreamed[remoteAddress] = 0
	wpcli.Tty = tty
	wpcli.LogFileName = logFileName
	wpcli.Running = true
	wpcli.padlock = &sync.Mutex{}
	gGUIDttys[Guid] = wpcli
	padlock.Unlock()

	prevState, err := terminal.MakeRaw(int(tty.Fd()))
	if nil != err {
		conn.Write([]byte("unable to initialize the remote WP CLI process."))
		conn.Close()
		logFile.Close()
		return errors.New(fmt.Sprintf("error initializing the WP CLI process: %s\n", err.Error()))
	}
	defer func() { _ = terminal.Restore(int(tty.Fd()), prevState) }()

	readFile, err := os.OpenFile(logFileName, os.O_RDONLY, os.ModeCharDevice)
	if nil != err {
		conn.Close()
		logFile.Close()
		return errors.New(fmt.Sprintf("error opening the read file for the stream: %s\n", err.Error()))
	}

	go func() {
		var written, read int
		var buf []byte = make([]byte, 8192)

		// Used to monitor when the connection is disconnected or the CLI command finishes
		ticker := time.Tick(time.Duration(500 * time.Millisecond.Nanoseconds()))

	Exit_Loop:
		for {
			select {
			case <-ticker:
				if (!wpcli.Running && wpcli.BytesStreamed[remoteAddress] >= wpcli.BytesLogged) || nil == conn {
					logger.Println("WP CLI command finished and all data has been written, exiting this watcher loop")
					break Exit_Loop
				}
			case ev := <-watcher.Event:
				if ev.IsDelete() {
					break Exit_Loop
				}
				if !ev.IsModify() {
					continue
				}

				read, err = readFile.Read(buf)
				if nil != err {
					if io.EOF != err {
						logger.Printf("error reading the log file: %s\n", err.Error())
						break Exit_Loop
					}
					continue
				}
				if 0 == read {
					continue
				}

				if nil == conn {
					logger.Println("client connection is closed, exiting this watcher loop")
					break Exit_Loop
				}

				written, err = conn.Write(buf[:read])

				wpcli.padlock.Lock()
				wpcli.BytesStreamed[remoteAddress] += int64(written)
				wpcli.padlock.Unlock()

				if nil != err {
					logger.Printf("error writing to client connection: %s\n", err.Error())
					break Exit_Loop
				}

			case err := <-watcher.Error:
				logger.Printf("error scanning the logfile %s: %s", logFileName, err.Error())
				break Exit_Loop
			}
		}

		logger.Println("closing watcher and read file")
		watcher.Close()
		readFile.Close()
	}()

	err = watcher.Watch(logFileName)
	if err != nil {
		conn.Close()
		logFile.Close()
		readFile.Close()
		return err
	}

	go func() {
		var written, read int
		var err error
		var buf []byte = make([]byte, 8192)

		for {
			if _, err = tty.Stat(); nil != err {
				// This is because the command has been terminated
				break
			}
			read, err = tty.Read(buf)
			atomic.AddInt64(&wpcli.BytesLogged, int64(read))

			if nil != err {
				if io.EOF != err {
					logger.Printf("error reading WP CLI tty output: %s\n", err.Error())
				}
				break
			}

			if 0 == read {
				continue
			}

			written, err = logFile.Write(buf[:read])
			if nil != err {
				logger.Printf("error writing to logfle: %s\n", err.Error())
				break
			}
			if written != read {
				logger.Printf("error writing to logfile, read %d and only wrote %d\n", read, written)
				break
			}
		}
		logger.Println("closing logfile and marking the WP-CLI as finished")
		logFile.Sync()
		logFile.Close()

		time.Sleep(time.Duration(50 * time.Millisecond.Nanoseconds()))

		wpcli.padlock.Lock()
		wpcli.Running = false
		wpcli.padlock.Unlock()
	}()

	go func() {
		processTCPConnectionData(conn, wpcli)
		conn.Close()
		conn = nil
	}()

	state, err := cmd.Process.Wait()
	if nil != err {
		logger.Printf("error from the wp command: %s\n", err.Error())
	}

	if !state.Exited() {
		logger.Println("terminating the wp command")
		conn.Write([]byte("Command has been terminated\n"))
		cmd.Process.Kill()
	}

	usage := state.SysUsage().(*syscall.Rusage)
	logger.Printf("Guid %s : max rss: %0.0f KB : user time %0.2f sec : sys time %0.2f sec",
		Guid,
		float64(usage.Maxrss)/1024,
		float64(usage.Utime.Sec)+float64(usage.Utime.Usec)/1e6,
		float64(usage.Stime.Sec)+float64(usage.Stime.Usec)/1e6)

	for {
		if (!wpcli.Running && wpcli.BytesStreamed[remoteAddress] >= wpcli.BytesLogged) || nil == conn {
			break
		}
		logger.Printf("waiting for remaining bytes to be sent to a client: at %d - have %d\n", wpcli.BytesStreamed[remoteAddress], wpcli.BytesLogged)
		time.Sleep(time.Duration(200 * time.Millisecond.Nanoseconds()))
	}

	if nil != conn {
		logger.Println("closing the connection at the end")
		conn.Close()
	}

	wpcli.padlock.Lock()
	logger.Printf("cleaning out %s\n", remoteAddress)
	delete(wpcli.BytesStreamed, remoteAddress)
	if 0 == len(wpcli.BytesStreamed) {
		logger.Printf("cleaning out %s\n", Guid)
		wpcli.padlock.Unlock()
		wpcli.padlock = nil
		padlock.Lock()
		delete(gGUIDttys, Guid)
		padlock.Unlock()
	} else {
		wpcli.padlock.Unlock()
	}

	return nil
}

func streamLogs(conn net.Conn, Guid string) {
	var err error
	var logFileName string

	logger.Printf("preparing to send the log file for Guid %s\n", Guid)

	if "os.Stdout" == logDest {
		logFileName = fmt.Sprintf("/tmp/wp-cli-%s", Guid)
	} else {
		logDir := path.Dir(logDest)
		logFileName = fmt.Sprintf("%s/wp-cli-%s", logDir, Guid)
	}

	if _, err := os.Stat(logFileName); nil != err {
		conn.Write([]byte(fmt.Sprintf("The WP CLI log file for Guid %s does not exist\n", Guid)))
		logger.Printf("The logfile %s does not exist\n", logFileName)
		conn.Close()
		return
	}

	logFile, err := os.OpenFile(logFileName, os.O_RDONLY|os.O_SYNC, 0666)
	if nil != err {
		conn.Write([]byte("error reading the WP CLI log file\n"))
		logger.Printf("error reading the WP CLI log file: %s\n", err.Error())
		conn.Close()
		return
	}

	var buf []byte = make([]byte, 8192)
	var read int
	for {
		read, err = logFile.Read(buf)
		if io.EOF == err {
			break
		}
		conn.Write(buf[:read])
	}
	conn.Close()
	logFile.Close()
	logger.Printf("log file for Guid %s sent\n", Guid)
}
