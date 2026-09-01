# syntax=docker/dockerfile:1

FROM golang:1.25-trixie AS energy-probe

WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY main.go ./
RUN CGO_ENABLED=0 go build -trimpath -o /energy-probe .

EXPOSE 9777

HEALTHCHECK --start-period=10s CMD [ "/energy-probe", "-healthcheck" ]

ENTRYPOINT [ "/energy-probe" ]
