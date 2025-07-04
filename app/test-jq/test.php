<?php
$inputFile = 'huge.json';
$outputFile = 'modi.jsonl';

// "jq -c '.products[]? | select((.type | ascii_downcase) == \"dish\")' %s > %s",

// Формируем команду с экранированием
$command = sprintf(    
    "jq -c '.products[]? | select((.type | ascii_downcase) == \"modifier\")' %s > %s",
    escapeshellarg($inputFile),
    escapeshellarg($outputFile)
);

// Выполняем команду
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo "Файл успешно преобразован!";
} else {
    echo "Ошибка (код $returnCode): " . implode("\n", $output);
}
