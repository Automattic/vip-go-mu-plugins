const { defineConfig } = require('cypress')

module.exports = defineConfig({
    "fixturesFolder": "tests/search/e2e/fixtures",
    "integrationFolder": "tests/search/e2e/integration",
    "pluginsFile": "tests/search/e2e/plugins/index.js",
    "screenshotsFolder": "tests/search/e2e/screenshots",
    "videosFolder": "tests/search/e2e/videos",
    "downloadsFolder": "tests/search/e2e/downloads",
    "supportFile": "tests/search/e2e/support/index.js",
    "video": false,
  
    "retries": {
      "runMode": 1
    },
    "elasticPressIndexTimeout": 100000,
    "numTestsKeptInMemory": 0,
    reporter: 'cypress-multi-reporters',
    reporterOptions: {
      configFile: 'tests/search/e2e/cypress-reporter-config.json'
    },
    experimentalSessionAndOrigin: true,
})
