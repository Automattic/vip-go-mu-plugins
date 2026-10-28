#!/usr/bin/env python3
"""Assert exact Docker-bound PHPUnit argv for both shell wrappers."""

import json
import os
from pathlib import Path
import subprocess
import tempfile


ROOT = Path(__file__).resolve().parent.parent
RUNNERS = (ROOT / "bin/test.sh", ROOT / "drop-ins/wp-memcached/bin/test.sh")
IMAGE = "ghcr.io/automattic/vip-container-images/wp-test-runner:latest"
DOCKER_STUB = """#!/usr/bin/env python3
import json
import os
import sys
from pathlib import Path

args = sys.argv[1:]
with Path(os.environ["DOCKER_LOG"]).open("a") as log:
    log.write(json.dumps(args) + "\\n")
if args[:1] == ["run"] and "--name" in args:
    print("owned-" + args[args.index("--name") + 1])
"""


def run(runner: Path, stub_dir: Path, log: Path, args):
    env = os.environ.copy()
    env.update(
        PATH=f"{stub_dir}:{env['PATH']}",
        DOCKER_LOG=str(log),
        CI="1",
        WPVIP_PARSELY_INTEGRATION_TEST_MODE="",
        WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION="",
    )
    result = subprocess.run([str(runner), *args], cwd=ROOT, env=env, capture_output=True, text=True)
    assert result.returncode == 0, (runner, result.stderr, result.stdout)
    events = [json.loads(line) for line in log.read_text().splitlines()]
    test_runs = [event for event in events if event[:1] == ["run"] and IMAGE in event]
    assert len(test_runs) == 1, events
    return test_runs[0]


def main():
    with tempfile.TemporaryDirectory(prefix="vip-runner-argv-") as directory:
        stub_dir = Path(directory)
        stub = stub_dir / "docker"
        stub.write_text(DOCKER_STUB)
        stub.chmod(0o755)
        filter_value = 'test_example with data set "two words"'
        for index, runner in enumerate(RUNNERS):
            default = run(runner, stub_dir, stub_dir / f"{index}-default.jsonl", [])
            assert default[default.index(IMAGE) + 1 :] == [], default
            ordinary = run(runner, stub_dir, stub_dir / f"{index}-ordinary.jsonl", ["--colors=never"])
            assert ordinary[ordinary.index(IMAGE) + 1 :] == ["--colors=never"], ordinary
            filtered = run(
                runner,
                stub_dir,
                stub_dir / f"{index}-filter.jsonl",
                ["--docker-options", "--label audit=1 --cpus 2", "--filter", filter_value],
            )
            assert filtered[filtered.index(IMAGE) + 1 :] == ["--filter", filter_value], filtered
            assert filtered[filtered.index("--label") + 1] == "audit=1"
            assert filtered[filtered.index("--cpus") + 1] == "2"
            wildcard = filter_value + " * [case]?"
            literal = run(
                runner,
                stub_dir,
                stub_dir / f"{index}-wildcard.jsonl",
                ["--filter", wildcard],
            )
            assert literal[literal.index(IMAGE) + 1 :] == ["--filter", wildcard], literal
            print(f"{runner.relative_to(ROOT)}: exact default, ordinary, and quoted filter argv passed")


if __name__ == "__main__":
    main()
