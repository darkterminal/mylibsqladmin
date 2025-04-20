group "default" {
  targets = ["web", "web_prod", "bridge", "proxy", "db"]
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

target "bridge" {
  context    = "./bridge"
  dockerfile = "Dockerfile"
  tags       = ["mylibsqladmin-bridge:latest"]
}

target "proxy" {
  dockerfile = "Dockerfile"  # You might need to create this
  tags       = ["mylibsqladmin-proxy:latest"]
}

target "db" {
  dockerfile = "Dockerfile"  # You might need to create this
  tags       = ["mylibsqladmin-db:latest"]
}
