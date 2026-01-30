variable "IMAGE_PREFIX" {
  default = "ghcr.io/cvvfcm/website/"
}

variable "TAGS" {
  default = "latest"
}

group "default" {
  targets = ["php", "consumer", "rtsp-to-web", "ml"]
}

target "php" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}php:${t}"]
  cache-from = ["type=registry,ref=${IMAGE_PREFIX}php:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}php:cache,mode=max"]
}

target "consumer" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}consumer:${t}"]
  cache-from = ["type=registry,ref=${IMAGE_PREFIX}consumer:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}consumer:cache,mode=max"]
}

target "ml" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}ml:${t}"]
  cache-from = ["type=registry,ref=${IMAGE_PREFIX}ml:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}ml:cache,mode=max"]
}

target "rtsp-to-web" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}rtsp-to-web:${t}"]
  cache-from = ["type=registry,ref=${IMAGE_PREFIX}rtsp-to-web:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}rtsp-to-web:cache,mode=max"]
}
