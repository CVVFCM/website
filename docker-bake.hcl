variable "IMAGE_PREFIX" {
  default = "ghcr.io/cvvfcm/website/"
}

variable "TAGS" {
  default = "latest"
}

group "default" {
  targets = ["php", "consumer", "rtsp-to-web", "backup"]
}

target "php" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}php:${t}"]

  platforms = ["linux/amd64", "linux/arm64"]

  cache-from = ["type=registry,ref=${IMAGE_PREFIX}php:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}php:cache,mode=max"]
}

target "consumer" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}consumer:${t}"]

  platforms = ["linux/amd64", "linux/arm64"]

  cache-from = ["type=registry,ref=${IMAGE_PREFIX}consumer:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}consumer:cache,mode=max"]
}

target "rtsp-to-web" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}rtsp-to-web:${t}"]

  platforms = ["linux/amd64", "linux/arm64"]

  cache-from = ["type=registry,ref=${IMAGE_PREFIX}rtsp-to-web:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}rtsp-to-web:cache,mode=max"]
}

target "backup" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}backup:${t}"]

  platforms = ["linux/amd64", "linux/arm64"]

  cache-from = ["type=registry,ref=${IMAGE_PREFIX}backup:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}backup:cache,mode=max"]
}
