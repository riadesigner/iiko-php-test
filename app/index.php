<?php

if ($_SERVER['REQUEST_URI'] !== '/') {
    echo "Nothing to see here.";
    exit;
}

// file_put_contents('hits.log', date('H:i:s.u') . PHP_EOL, FILE_APPEND);
// echo "Hello!";

file_put_contents('hits.log', date('H:i:s.u') . ' ' . $_SERVER['REQUEST_URI'] . PHP_EOL, FILE_APPEND);
echo "Hello!";

exit();
define("BASEPATH",__file__);

require_once('config.php');
require_once('common.php');
require_once('class.iiko_params_test.php');



// $iiko_params = new iiko_params_test(100,$_ENV["API_KEY"]);
// $iiko_params->reload();
// $data = $iiko_params->get();

// try {
//     $savedFile = saveArrayToUniqueJson($data, "files");
//     echo "File saved: " . $savedFile;
// } catch (RuntimeException $e) {
//     echo "Error: " . $e->getMessage();
// }

echo "1<br>";


function saveArrayToUniqueJson_old(array $data, string $directory = 'storage'): ?string {

    error_log("saveArrayToUniqueJson CALLED");
    glog("функция вызвана!");
    echo "A<br>";

    // Создаем директорию, если её нет
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException("Failed to create directory: $directory");
    }

    // Генерируем уникальное имя файла
    do {
        $filename = sprintf(
            '%s/%s_%s.json',
            $directory,
            date('Y-m-d_H-i-s'),
            bin2hex(random_bytes(4))
        );
    } while (file_exists($filename));

    // Кодируем данные
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        throw new RuntimeException('JSON encoding failed');
    }

    // Сохраняем в файл
    if (file_put_contents($filename, $json, LOCK_EX) === false) {
        throw new RuntimeException("Failed to write file: $filename");
    }

    return $filename;
}

function saveArrayToUniqueJson(array $data, string $directory = 'storage'): ?string {

    echo "<br>wow<br>";

    // Создаем директорию, если её нет
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException("Failed to create directory: $directory");
    }

    // Генерируем уникальное имя для финального файла
    do {
        $finalFilename = sprintf(
            '%s/%s_%s.json',
            $directory,
            date('Y-m-d_H-i-s'),
            bin2hex(random_bytes(4))
        );
    } while (file_exists($finalFilename));

    // Кодируем данные
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('JSON encoding failed');
    }

    // Создаем временный файл
    $tempFile = tempnam(sys_get_temp_dir(), 'json_');
    if ($tempFile === false) {
        throw new RuntimeException('Failed to create temporary file');
    }

    // Записываем JSON во временный файл
    if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
        unlink($tempFile); // удаляем временный файл в случае неудачи
        throw new RuntimeException("Failed to write to temporary file");
    }

    // Перемещаем временный файл в финальное место
    if (!rename($tempFile, $finalFilename)) {
        unlink($tempFile);
        throw new RuntimeException("Failed to move file to: $finalFilename");
    }

    return $finalFilename;
}

// Пример использования
try {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'hobbies' => ['reading', 'gaming']
    ];
    
    $savedFile = saveArrayToUniqueJson($data);
    echo "File saved: " . $savedFile;
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage();
}

print_r(glob('storage/*.json'));

?>