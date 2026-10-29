# Build ZIP archive with 7-Zip
7z a "DocEditor.zip" `
"app" `
"assets" `
"config" `
"database" `
"public" `
"resources" `
"routes" `
"storage" `
".env" `
".env.example" `
"bootstrap.php" `
"composer.json" `
"index.php" `
"README.md" `

Write-Host "Archive created: DocEditor.zip"
