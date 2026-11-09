#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_TAG_TEST="php-mb-helpers-system-test:7.2.34"
IMAGE_TAG_FLOOR="php-mb-helpers-floor-lint:5.3.29"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_cmd build -f "${REPO_ROOT}/docker/system-test/Dockerfile" -t "${IMAGE_TAG_TEST}" "${REPO_ROOT}"
docker_cmd build -f "${REPO_ROOT}/docker/floor-lint/Dockerfile" -t "${IMAGE_TAG_FLOOR}" "${REPO_ROOT}"

docker_cmd run --rm \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    "${IMAGE_TAG_TEST}" \
    bash /app/scripts/system-test-inner.sh

docker_cmd run --rm \
    --entrypoint bash \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    "${IMAGE_TAG_FLOOR}" \
    /app/scripts/system-test-floor.sh
