#!/usr/bin/env python3
"""Check runner resource ownership without starting Docker or PHPUnit."""

import json
import os
from pathlib import Path
import subprocess
import tempfile


ROOT = Path(__file__).resolve().parent.parent
RUNNERS = (ROOT / "bin/test.sh", ROOT / "drop-ins/wp-memcached/bin/test.sh")
DOCKER_STUB = """#!/usr/bin/env python3
import json
import os
import sys
from pathlib import Path

args = sys.argv[1:]
with Path(os.environ["DOCKER_LOG"]).open("a") as log:
    log.write(json.dumps(args) + "\\n")
if args[:1] == ["run"]:
    if "--name" in args:
        print("owned-" + args[args.index("--name") + 1])
    elif os.environ.get("FAIL_TEST_RUN") == "1":
        sys.exit(7)
"""
DATE_STUB = """#!/bin/sh
case "$1" in
    +%s) printf '1700000000\\n' ;;
    +%s000) printf '1700000000000\\n' ;;
    *) exit 2 ;;
esac
"""


def run(runner: Path, stub_dir: Path, log: Path, *, fail: bool = False, overrides: bool = False):
    env = os.environ.copy()
    env.update(
        PATH=f"{stub_dir}:{env['PATH']}",
        DOCKER_LOG=str(log),
        FAIL_TEST_RUN="1" if fail else "0",
        CI="1",
        WPVIP_PARSELY_INTEGRATION_TEST_MODE="",
        WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION="",
    )
    args = [str(runner)]
    if runner == RUNNERS[0]:
        args.append("--memcached")
    if overrides:
        args += ["--network", "caller-network", "--dbhost", "caller-db"]
    result = subprocess.run(args, cwd=ROOT, env=env, capture_output=True, text=True)
    assert result.returncode == (7 if fail else 0), (runner, result.stderr, result.stdout)
    return [json.loads(line) for line in log.read_text().splitlines()]


def check(events, *, overrides: bool, cache_count: int):
    created_networks = [event[2] for event in events if event[:2] == ["network", "create"]]
    removed_networks = [event[2] for event in events if event[:2] == ["network", "rm"]]
    named_runs = [event for event in events if event[:1] == ["run"] and "--name" in event]
    names = {event[event.index("--name") + 1] for event in named_runs}
    removed_containers = {event[2] for event in events if event[:2] == ["rm", "-f"]}
    runner_runs = [event for event in events if event[:1] == ["run"] and "--name" not in event]
    assert len(runner_runs) == 1
    assert len(names) == cache_count + (0 if overrides else 1), events
    assert removed_containers == {"owned-" + name for name in names}, events
    assert all(event[event.index("--network") + 1] == ("caller-network" if overrides else created_networks[0]) for event in named_runs + runner_runs)
    if overrides:
        assert not created_networks and not removed_networks
        assert "caller-db" not in removed_containers
    else:
        assert len(created_networks) == 1 and removed_networks == created_networks
    return created_networks, names


def main():
    with tempfile.TemporaryDirectory(prefix="vip-runner-ownership-") as directory:
        stub_dir = Path(directory)
        for name, content in (("docker", DOCKER_STUB), ("date", DATE_STUB)):
            path = stub_dir / name
            path.write_text(content)
            path.chmod(0o755)
        for runner in RUNNERS:
            log_dir = stub_dir / ("root" if runner == RUNNERS[0] else "memcached")
            log_dir.mkdir()
            cache_count = 1 if runner == RUNNERS[0] else 2
            first = check(run(runner, stub_dir, log_dir / "first.jsonl"), overrides=False, cache_count=cache_count)
            second = check(run(runner, stub_dir, log_dir / "second.jsonl"), overrides=False, cache_count=cache_count)
            assert first[0] != second[0], (runner, first, second)
            assert first[1].isdisjoint(second[1]), (runner, first, second)
            check(run(runner, stub_dir, log_dir / "failed.jsonl", fail=True), overrides=False, cache_count=cache_count)
            check(run(runner, stub_dir, log_dir / "override.jsonl", overrides=True), overrides=True, cache_count=cache_count)
            print(f"{runner.relative_to(ROOT)}: same-clock identities and owned cleanup passed")


if __name__ == "__main__":
    main()
