variable "IMAGE_PREFIX" {
  default = "ghcr.io/cvvfcm/website/"
}

variable "TAGS" {
  default = "latest"
}

group "default" {
  targets = ["php", "rtsp-to-web", "ml"]
}

target "php" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}php:${t}"]
}

target "ml" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}ml:${t}"]
}

target "rtsp-to-web" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}rtsp-to-web:${t}"]
}
