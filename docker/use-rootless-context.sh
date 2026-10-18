if [ -z "${DOCKER_CONTEXT:-}" ] && [ -z "${DOCKER_HOST:-}" ]; then
    if docker --context rootless info >/dev/null 2>&1; then
        DOCKER_CONTEXT=rootless
        export DOCKER_CONTEXT
    fi
fi
