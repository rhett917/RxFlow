#!/usr/bin/env Rscript

# R Package Installation Script for RxFlow

cat("Installing R packages for RxFlow...\n")

# Set CRAN mirror
options(repos = c(CRAN = "https://cloud.r-project.org/"))

# List of required packages
packages <- c(
  "jsonlite",     # JSON parsing
  "stringr",      # String manipulation
  "dplyr",        # Data manipulation
  "stringdist",   # Fuzzy string matching
  "tidyr",        # Data tidying
  "readr"         # Fast CSV reading
)

# Function to install packages
install_if_missing <- function(pkg) {
  if (!require(pkg, character.only = TRUE)) {
    cat(paste("Installing", pkg, "...\n"))
    install.packages(pkg, dependencies = TRUE)
    if (!require(pkg, character.only = TRUE)) {
      stop(paste("Failed to install", pkg))
    }
  } else {
    cat(paste(pkg, "already installed\n"))
  }
}

# Install all packages
for (pkg in packages) {
  install_if_missing(pkg)
}

# Verify installation
cat("\nVerifying installations:\n")
for (pkg in packages) {
  if (require(pkg, character.only = TRUE)) {
    cat(paste("✓", pkg, "version", packageVersion(pkg), "\n"))
  } else {
    cat(paste("✗", pkg, "not installed\n"))
  }
}

cat("\nR package installation complete!\n")