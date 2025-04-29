group "development" {
  targets = ["web", "proxy", "db"]
}

group "production" {
  targets = ["web_prod", "proxy", "db"]
}

target "web" {
  context    = "./admin"
  dockerfile = "Dockerfile"
  args = {
    APP_ENV  = "development"
    ENV_FILE = ".env.local"  # Changed to match compose.yml
  }
  tags = ["mylibsqladmin-web:development"]
}

target "web_prod" {
  context    = "./admin"
  dockerfile = "Dockerfile"
  args = {
    APP_ENV  = "production"
    ENV_FILE = ".env.production"  # Corrected path to match compose.yml
  }
  tags = ["mylibsqladmin-web:production"]
}

target "proxy" {
  tags       = ["mylibsqladmin-proxy:latest"]
}

target "db" {
  tags       = ["mylibsqladmin-db:latest"]
}
