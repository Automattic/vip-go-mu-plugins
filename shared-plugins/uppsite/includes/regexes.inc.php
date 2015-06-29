<?php
/**
 * Regex container
 */
$regexes = array(
    /** Contains a regex that should identify US addresses. */
    'address' => '/(?:[([0-9][^\n]+?\n)([^\n]+?\n){0,2}([^\n]+?)(AL|ALABAMA|AK|ALASKA|AZ|ARIZONA|AR|ARKANSAS|CA|CALIFORNIA|CO|COLORADO|CT|CONNECTICUT|DE|DELAWARE|FL|FLORIDA|GA|GEORGIA|HI|HAWAII|ID|IDAHO|IL|ILLNOIS|IN|INDIANA|IA|IOWA|KS|KANSAS|KY|KENTUCKY|LA|LOUISIANA|ME|MAINE|MD|MARYLAND|MA|MASSACHUSETTS|MI|MICHIGAN|MN|MINNESOTA|MS|MISSISSIPPI|MO|MISSOURI|MT|MONTANA|NE|NEBRASKA|NV|NEVADA|NH|NEW HAMPSHIRE|NJ|NEW JERSEY|NM|NEW MEXICO|NY|NEW YORK|NC|NORTH CAROLINA|ND|NORTH DAKOTA|OH|OHIO|OK|OKLAHOMA|OR|OREGON|PA|PENNSYLVANIA|RI|RHODE ISLAND|SC|SOUTH CAROLINA|SD|SOUTH DAKOTA|TN|TENNESSEE|TX|TEXAS|UT|UTAH|VT|VERMONT|VA|VIRGINIA|WA|WASHINGTON|DC|DISTRICT OF COLUMBIA|WASHINGTON DC)\s*([0-9\-|]{5,10})/ms',
    /** Phone number regex */
    'phone' => "/([1](\.|-|\s))?(\(?)[0-9]{3}(\)?)(\.|-|\s)[0-9]{3}(\.|-|\s)[0-9]{4}/",
    /** Weak regex of phone number (if previous didn't catch a thing) */
    'phone_weak' => "/(((\d){2,6}[\s\-\.]){3,6})/",
    /** Email address regex */
    'email' => '/[_a-z0-9-]+(\.[_a-z0-9-]+)*([ ]{0,2})@[a-z0-9- ]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/i'
);