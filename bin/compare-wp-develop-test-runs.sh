if [ $# -lt 4 ]; then
	echo "$0 - compares phpunit reports."
    echo "If the difference in errors and failures are withint provided margins, script succeds fails otherwise"
    echo 
    echo "usage: $0 <core-report-file> <core-with-mu-plugins-report-file> <failure-margin> <error-margin>"
	exit 1
fi

CORE_REPORT=$1
MU_PLUGINS_REPORT=$2
FAILS_MARGIN=$3
ERROR_MARGIN=$4

getSummaryLineFromReport () {
    cat "$1" | grep -o '<testsuite name="".*>'
}

extractValueFromSummaryLine() {
    getSummaryLineFromReport "$1" | grep -o "$2=\"[0-9]*\"" | grep -o '[0-9]*'
}

evaluateDifferences() {
    local attributes=("tests" "failures" "errors");
    local margins=(0 "$FAILS_MARGIN" "$ERROR_MARGIN");
    local valueDiffs=();

    # Calculate and print differences
    for ix in "${!attributes[@]}"; do
        local attribute=${attributes[$ix]} 
        local valueCore=$(extractValueFromSummaryLine "$CORE_REPORT" "$attribute")
        local valueMU=$(extractValueFromSummaryLine "$MU_PLUGINS_REPORT" "$attribute")
        local valueDiff=$(( "$valueMU" - "$valueCore" ))
        echo "$attribute: $valueCore $valueMU => $valueDiff"
        valueDiffs+=( "$valueDiff" );
    done

    # Check differences against the margins
    for ix in "${!attributes[@]}"; do
        local attribute=${attributes[$ix]} 
        if [ "${valueDiffs[$ix]}" -gt "${margins[$ix]}" ]; then
            echo "ERROR: ${attributes[$ix]} difference (${valueDiffs[$ix]}) is more than ${margins[$ix]}";
            exit 1;
        fi
    done
}

evaluateDifferences
