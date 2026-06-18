set shell := ["bash", "-cu"]

mago := "tools/mago/vendor/bin/mago"
# Docker Compose binary: docker-compose on macOS, docker compose elsewhere (mirrors the old Makefile).
compose := if os() == "macos" { "docker-compose" } else { "docker compose" }

# List available recipes.
default:
    @just --list

# --- Docker stack (run from the host) ---

# Build images, start the stack, and install PHP dependencies.
install:
    {{compose}} build
    {{compose}} up -d
    {{compose}} run app composer install

# Start the stack (detached).
start:
    {{compose}} up -d

# Stop the stack and remove orphans.
stop:
    {{compose}} down --remove-orphans

# Open a bash shell in the app container.
bash:
    {{compose}} exec app bash

# Kill all running containers.
kill-all:
    docker container ls -q | xargs -r docker container kill

# --- Quality gate (run in nix-shell or the app container) ---

# Run linters: Mago format check + Mago lint.
lint:
    {{mago}} format --check
    {{mago}} lint

# Run static analysis (Mago).
analyze *args:
    {{mago}} analyze {{args}}

# Auto-fix code style (Mago format + lint --fix).
fix:
    {{mago}} format
    {{mago}} lint --fix --potentially-unsafe --format-after-fix

# Validate architecture (Deptrac).
deptrac:
    composer deptrac

# Run the PHPUnit suite (migrates the test database first).
test:
    composer db:test
    composer test

# Static gate (no database required) - mirrors the lint/analyze/architecture CI steps.
check: lint analyze deptrac
