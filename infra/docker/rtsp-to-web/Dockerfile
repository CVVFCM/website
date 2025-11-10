FROM ghcr.io/deepch/rtsptoweb:latest  AS rtsp-to-web

LABEL org.opencontainers.image.source=https://github.com/CVVFCM/website
LABEL org.opencontainers.image.authors="Yohan Giarelli <yohan@cvvfcm.fr>"

RUN set -eux; \
    wget https://github.com/a8m/envsubst/releases/download/v1.4.3/envsubst-`uname -s`-`uname -m` -O envsubst; \
    chmod +x envsubst; \
    mv envsubst /usr/local/bin

ADD ./config.json /config/config.json.template
ADD ./docker-entrypoint.sh /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["./rtsp-to-web", "--config=/config/config.json"]
