#!/usr/bin/env Rscript

# Load required libraries
library(jsonlite)
library(stringr)
library(dplyr)

# Function to validate prescription data
validate_prescription <- function(json_input) {
  # Parse input
  data <- fromJSON(json_input)
  
  # Initialize validation results
  validation_results <- list(
    is_valid = TRUE,
    errors = list(),
    warnings = list(),
    confidence = 1.0,
    validated_data = data$parsed_data
  )
  
  # Load medication database
  med_db <- read.csv("/data/dictionaries/medications.csv", stringsAsFactors = FALSE)
  
  # Validate medications
  if (!is.null(data$parsed_data$medications)) {
    for (i in seq_along(data$parsed_data$medications)) {
      med <- data$parsed_data$medications[[i]]
      
      # Check medication name
      med_match <- validate_medication_name(med$name, med_db)
      if (!med_match$found) {
        validation_results$warnings <- append(
          validation_results$warnings,
          paste("Medication not found in database:", med$name)
        )
        validation_results$confidence <- validation_results$confidence * 0.8
      } else {
        # Update with canonical name
        validation_results$validated_data$medications[[i]]$name <- med_match$canonical_name
      }
      
      # Validate dosage
      dosage_valid <- validate_dosage(med$dosage, med_match$canonical_name, med_db)
      if (!dosage_valid$is_valid) {
        validation_results$errors <- append(
          validation_results$errors,
          paste("Invalid dosage for", med$name, ":", dosage_valid$message)
        )
        validation_results$is_valid <- FALSE
      }
    }
  }
  
  # Validate doctor CRM
  if (!is.null(data$parsed_data$crm)) {
    crm_valid <- validate_crm(data$parsed_data$crm)
    if (!crm_valid) {
      validation_results$errors <- append(
        validation_results$errors,
        "Invalid CRM number format"
      )
      validation_results$is_valid <- FALSE
    }
  }
  
  # Calculate final confidence
  validation_results$confidence <- validation_results$confidence * data$confidence
  
  # Return JSON result
  toJSON(validation_results, auto_unbox = TRUE)
}

# Validate medication name using fuzzy matching
validate_medication_name <- function(med_name, database) {
  # Clean input
  clean_name <- toupper(trimws(med_name))
  
  # Exact match
  exact_match <- database[toupper(database$name) == clean_name, ]
  if (nrow(exact_match) > 0) {
    return(list(found = TRUE, canonical_name = exact_match$name[1]))
  }
  
  # Fuzzy match
  distances <- stringdist::stringdist(clean_name, toupper(database$name))
  min_dist <- min(distances)
  
  if (min_dist <= 2) {  # Allow up to 2 character differences
    best_match <- database$name[which.min(distances)]
    return(list(found = TRUE, canonical_name = best_match))
  }
  
  return(list(found = FALSE, canonical_name = med_name))
}

# Validate dosage
validate_dosage <- function(dosage, med_name, database) {
  # Extract numeric value and unit
  dosage_parts <- str_match(dosage, "(\\d+\\.?\\d*)\\s*(mg|ml|g|mcg)")
  
  if (is.na(dosage_parts[1])) {
    return(list(is_valid = FALSE, message = "Invalid dosage format"))
  }
  
  value <- as.numeric(dosage_parts[2])
  unit <- dosage_parts[3]
  
  # Get medication info
  med_info <- database[toupper(database$name) == toupper(med_name), ]
  
  if (nrow(med_info) > 0) {
    min_dose <- med_info$min_dose[1]
    max_dose <- med_info$max_dose[1]
    expected_unit <- med_info$unit[1]
    
    # Check unit
    if (unit != expected_unit) {
      return(list(is_valid = FALSE, message = paste("Expected unit:", expected_unit)))
    }
    
    # Check range
    if (value < min_dose || value > max_dose) {
      return(list(
        is_valid = FALSE, 
        message = paste("Dosage out of range. Expected:", min_dose, "-", max_dose, unit)
      ))
    }
  }
  
  return(list(is_valid = TRUE))
}

# Validate CRM
validate_crm <- function(crm) {
  # Brazilian CRM format validation
  return(grepl("^\\d{4,6}$", crm))
}

# Main execution
args <- commandArgs(trailingOnly = TRUE)
if (length(args) > 0) {
  result <- validate_prescription(args[1])
  cat(result)
} else {
  cat('{"error": "No input provided"}')
}