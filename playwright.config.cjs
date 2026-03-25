const { defineConfig, devices } = require("@playwright/test");

module.exports = defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: false,
  outputDir: process.env.PLAYWRIGHT_TEST_OUTPUT_DIR || "./test-results",
  timeout: 60 * 1000,
  workers: 1,
  expect: {
    timeout: 10 * 1000,
  },
  reporter: [
    ["list"],
    [
      "html",
      {
        open: "never",
        outputFolder:
          process.env.PLAYWRIGHT_HTML_REPORT_DIR || "./playwright-report",
      },
    ],
  ],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:8080",
    headless: true,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: {
        ...devices["Desktop Chrome"],
      },
    },
  ],
});
