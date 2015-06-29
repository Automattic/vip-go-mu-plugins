README_FILENAME=README.md
SCRIPT_FILENAME=scrollkit-wp.php
PWD=`pwd`
VERSION=$(shell awk '/Version: (.+)$$/ {print $$2}' "${SCRIPT_FILENAME}")
zip:
	rm -f "${PWD}/scrollkit-wp-${VERSION}.zip"
	zip -r "${PWD}/scrollkit-wp-${VERSION}.zip" * -x tests\/ tests/* phpunit.xml Makefile TODO *.sh 
