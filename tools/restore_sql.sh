#!/bin/bash
# Restore FA SQL backup for UAT testing
# Usage: ./restore_sql.sh <source_sql_file> <target_company_prefix>
# Example: ./restore_sql.sh backup.sql 0_

set -e

if [ $# -lt 2 ]; then
    echo "Usage: $0 <source_sql_file> <target_prefix>"
    echo "Example: $0 backup.sql 0_"
    exit 1
fi

SOURCE_FILE="$1"
TARGET_PREFIX="$2"

OUTPUT_FILE="uat_import_$(date +%Y%m%d_%H%M%S).sql"

echo "Converting $SOURCE_FILE -> $OUTPUT_FILE"
echo "Target prefix: $TARGET_PREFIX"

# Process the SQL file
# Skip headers, structure sections, DROP, CREATE, and security/user tables
awk -v target="$TARGET_PREFIX" '
BEGIN { 
    in_create = 0
    skip_table = 0
}

# Skip DROP TABLE lines
/^DROP TABLE IF EXISTS/ { next }

# Detect start of CREATE TABLE - skip until semicolon
/^CREATE TABLE/ { in_create = 1; next }
in_create && /;$/ { in_create = 0; next }
in_create { next }

# Skip structure headers  
/^### Structure of table/ { next }

# Detect table name in data header
/^### Data of table/ {
    # Check if this is a security/user table
    if (/security_roles|useronline|users/) {
        skip_table = 1
    } else {
        skip_table = 0
    }
    next
}

# Skip lines for security/user tables
skip_table { next }

# Convert INSERT to INSERT IGNORE and change prefix (handles both 1_ and 0_ source)
/^INSERT INTO `0_/ {
    gsub(/^INSERT INTO/, "INSERT IGNORE INTO")
}
/^INSERT INTO `1_/ {
    gsub(/`1_/, "`" target)
    gsub(/^INSERT INTO/, "INSERT IGNORE INTO")
}

# Remove empty lines
/^[[:space:]]*$/ { next }

{ print }
' "$SOURCE_FILE" > "$OUTPUT_FILE"

echo "Done. Output: $OUTPUT_FILE ($(wc -l < "$OUTPUT_FILE") lines)"