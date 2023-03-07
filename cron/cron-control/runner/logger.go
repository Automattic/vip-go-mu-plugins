package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"os"
	"path"
	"sync"
	"time"
)

type LogType int

const (
	Text LogType = iota
	JSON
)

type LogEntry struct {
	Timestamp string `json:"ts"`
	Message   string `json:"msg"`
}

type Logger struct {
	FileName string
	Type     LogType
	l        *log.Logger
	f        *os.File
	logMutex *sync.Mutex
}

func (self *Logger) Init() {
	self.logMutex = &sync.Mutex{}

	if "os.Stdout" == self.FileName {
		self.l = log.New(os.Stdout, "", log.Ldate|log.Ltime|log.LUTC|log.Lshortfile)
		return
	}

	err := self.dirCreateIfNotExists(self.FileName)
	if nil != err {
		fmt.Printf("Error creating the logging directory: %s\n", err.Error())
		os.Exit(1)
	}

	err = self.openLogFile()
	if nil != err {
		fmt.Printf("Error opening the log file: %s\n", err.Error())
		os.Exit(1)
	}
}

func (self *Logger) Println(v ...interface{}) {
	self.logMutex.Lock()
	var err error

	switch self.Type {
	case Text:
		err = self.l.Output(2, fmt.Sprintln(v...))
	case JSON:
		str := fmt.Sprintln(v...)
		if 0 < len(str) && '\n' == str[len(str)-1] {
			str = str[:len(str)-1]
		}
		var buf []byte
		var jsonErr error
		buf, jsonErr = json.Marshal(LogEntry{Message: str, Timestamp: time.Now().Format("2006/01/02 15:04:05.000")})
		if nil == jsonErr {
			_, err = self.f.WriteString(string(buf) + "\n")
		}
	}

	if nil != err {
		fmt.Println(err.Error())
		self.f.Close()
		self.openLogFile()
	}
	self.logMutex.Unlock()
}

func (self *Logger) Printf(str string, v ...interface{}) {
	self.logMutex.Lock()
	var err error
	switch self.Type {
	case Text:
		err = self.l.Output(2, fmt.Sprintf(str, v...))
	case JSON:
		if 0 < len(str) && '\n' == str[len(str)-1] {
			str = str[:len(str)-1]
		}
		var buf []byte
		var jsonErr error
		buf, jsonErr = json.Marshal(LogEntry{Message: fmt.Sprintf(str, v...), Timestamp: time.Now().Format("2006/01/02 15:04:05.000")})
		if nil == jsonErr {
			_, err = self.f.WriteString(string(buf) + "\n")
		}
	}
	if nil != err {
		fmt.Println(err.Error())
		self.f.Close()
		self.openLogFile()
	}
	self.logMutex.Unlock()
}

func (self *Logger) Fatal(v ...interface{}) {
	self.Println(v...)
	os.Exit(1)
}

func (self *Logger) Fatalf(str string, v ...interface{}) {
	self.Printf(str, v...)
	os.Exit(1)
}

func (self *Logger) dirCreateIfNotExists(FileName string) error {
	dir := path.Dir(FileName)
	if _, err := os.Stat(dir); os.IsNotExist(err) {
		err = os.MkdirAll(dir, 0777)
		if nil != err {
			return errors.New(fmt.Sprintf("error creating the log directory: %s", err.Error()))
		}
	}
	return nil
}

func (self *Logger) openLogFile() error {
	f, err := os.OpenFile(self.FileName, os.O_RDWR|os.O_CREATE|os.O_APPEND, 0666)
	if nil != err {
		return errors.New(fmt.Sprintf("error opening logfile: %s", err.Error()))
	}
	self.f = f

	if Text == self.Type {
		self.l = log.New(self.f, "", log.Ldate|log.Ltime|log.LUTC|log.Lshortfile)
	}
	return nil
}
