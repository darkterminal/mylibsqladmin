group "default" {
  targets = ["web", "web_prod", "bridge"]
}

target "web" {
  context    = "./admin"
  dockerfile = "Dockerfile"
  args = {
    APP_ENV  = "development"
    ENV_FILE = ".env"
  }
  tags = ["mylibsqladmin-web:development"]
}

target "web_prod" {
  context    = "./admin"
  dockerfile = "Dockerfile"
  args = {
    APP_ENV  = "production"
    ENV_FILE = ".env.production"
  }
  tags = ["mylibsqladmin-web:production"]
}

target "bridge" {
  context    = "./bridge"
  dockerfile = "Dockerfile"
  tags       = ["mylibsqladmin-bridge:latest"]
}
