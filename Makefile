MODULE_NAME := redcap-random-followup
VERSION := $(shell jq -r '.version // "dev"' config.json 2>/dev/null || echo dev)
RELEASE_DIR := dist/$(MODULE_NAME)_$(VERSION)

.PHONY: release clean test zip

## Run unit tests
test:
	@echo "Running tests..."
	@./vendor/bin/phpunit

## Create release folder (REDCap-ready)
release: clean test
	@echo "Creating release: $(RELEASE_DIR)"
	@mkdir -p $(RELEASE_DIR)

	# Core module files
	@cp RandomFollowup.php $(RELEASE_DIR)/
	@cp Scheduler.php $(RELEASE_DIR)/
	@cp config.json $(RELEASE_DIR)/
	@cp README.md $(RELEASE_DIR)/
	@cp LICENSE $(RELEASE_DIR)/

	# Optional docs
	@if [ -d docs ]; then cp -r docs $(RELEASE_DIR)/; fi

	@echo "Release created at $(RELEASE_DIR)"
	@echo "Ready to copy into REDCap modules directory."

## Clean release artifacts
clean:
	@rm -rf dist

zip: release
	@cd dist && zip -r $(MODULE_NAME)_$(VERSION).zip $(MODULE_NAME)_$(VERSION)
	@echo "Created dist/$(MODULE_NAME)_$(VERSION).zip"
