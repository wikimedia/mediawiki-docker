FROM alpine:latest

# Versions from https://github.com/wikimedia/parsoid/releases
ENV PARSOID_VERSION=v0.10.0
ENV PARSOID_PATH=/usr/src/app/

RUN apk add --no-cache nodejs nodejs-npm python git make \
    && mkdir -p ${PARSOID_PATH} \
    && git clone \
        --branch ${PARSOID_VERSION} \
        --single-branch \
        --depth 1 \
        --quiet \
        https://github.com/wikimedia/parsoid \
        $PARSOID_PATH \
    && cd $PARSOID_PATH \
    && npm install

EXPOSE 8000

COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
