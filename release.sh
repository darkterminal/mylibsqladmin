#!/bin/bash

# 🚀 Usage: ./build.sh <build_type>
# <build_type> should be one of: patch, minor, major

BUILD_TYPE=$1

# ❓ Ensure build type is provided
if [[ -z "$BUILD_TYPE" ]]; then
    echo "❌ Error: Build type (patch, minor, major) must be specified."
    exit 1
fi

# ✅ Validate build type
if [[ "$BUILD_TYPE" != "patch" && "$BUILD_TYPE" != "minor" && "$BUILD_TYPE" != "major" ]]; then
    echo "❌ Error: Invalid build type. Allowed types are: patch, minor, major."
    exit 1
fi

# 📦 Step 1: Update composer.version in composer.json
echo "🔄 Updating version in composer.json..."
CURRENT_VERSION=$(jq -r '.version' ./webapp/composer.json)
IFS='.' read -r -a VERSION_PARTS <<<"$CURRENT_VERSION"

if [[ "$BUILD_TYPE" == "patch" ]]; then
    VERSION_PARTS[2]=$((VERSION_PARTS[2] + 1))
elif [[ "$BUILD_TYPE" == "minor" ]]; then
    VERSION_PARTS[1]=$((VERSION_PARTS[1] + 1))
    VERSION_PARTS[2]=0
elif [[ "$BUILD_TYPE" == "major" ]]; then
    VERSION_PARTS[0]=$((VERSION_PARTS[0] + 1))
    VERSION_PARTS[1]=0
    VERSION_PARTS[2]=0
fi

NEW_VERSION="${VERSION_PARTS[0]}.${VERSION_PARTS[1]}.${VERSION_PARTS[2]}"

# Update the composer.json file
jq --arg new_version "$NEW_VERSION" '.version = $new_version' ./webapp/composer.json >./webapp/composer_temp.json && mv ./webapp/composer_temp.json ./webapp/composer.json
cd webapp && composer update && cd ..
echo "✅ Updated version to $NEW_VERSION in composer.json"

# 📜 Step 2: Update CHANGELOG.md
echo "📜 Updating CHANGELOG.md..."
PREVIOUS_TAG=$(git describe --tags --abbrev=0 2>/dev/null) || true

if [[ -z "$PREVIOUS_TAG" ]]; then
    echo "📝 Generating changelog from initial commit..."
    CHANGELOG_ENTRIES=$(git log --pretty=format:"- %s [%an]")
else
    echo "📝 Generating changelog from $PREVIOUS_TAG to HEAD..."
    CHANGELOG_ENTRIES=$(git log "$PREVIOUS_TAG"..HEAD --pretty=format:"- %s [%an]")
fi

# Prepend new changelog entries
echo -e "## [$NEW_VERSION] - $(date +"%Y-%m-%d")\n\n$CHANGELOG_ENTRIES\n\n$(cat CHANGELOG.md)" >CHANGELOG.md
echo "✅ Updated CHANGELOG.md"

# 📝 Step 3: Git commit and tag
echo "🔨 Committing changes..."
git add .
git commit -m "release: $BUILD_TYPE version $NEW_VERSION"
if [[ $? -ne 0 ]]; then
    echo "❌ Error: Git commit failed."
    exit 1
fi

# 🔖 Step 4: Create a new tag
echo "🏷️ Creating new git tag..."
git tag "v$NEW_VERSION" -m "Release $BUILD_TYPE version v$NEW_VERSION"
if [[ $? -ne 0 ]]; then
    echo "❌ Error: Git tag creation failed."
    exit 1
fi

# 🔄 Step 5: Push changes to remote
echo "⬆️ Pushing changes to remote repository..."
git push origin main
if [[ $? -ne 0 ]]; then
    echo "❌ Error: Git push to main branch failed."
    exit 1
fi

git push --tags
if [[ $? -ne 0 ]]; then
    echo "❌ Error: Git push tags failed."
    exit 1
fi

echo "🎉 Release $BUILD_TYPE version $NEW_VERSION completed successfully!"
