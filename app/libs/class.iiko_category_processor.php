<?php

class IikoCategoryProcessor {
    private $outputDir;
    private $tempFile;
    private $processedFiles = [];
    
    public function __construct() {
        $this->outputDir = dirname(__FILE__) . '/categories';
        $this->tempFile = $this->outputDir . '/temp_response.json';
        
        // Создаем директорию если нет
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Основной метод: получает данные и извлекает категории
     */
    public function extractCategories($url, $headers, $params) {
        // Шаг 1: Скачиваем JSON во временный файл
        $this->downloadJsonToFile($url, $headers, $params);
        
        // Шаг 2: Извлекаем категории в отдельные файлы через jq
        $this->extractCategoriesWithJq();
        
        // Шаг 3: Получаем список созданных файлов
        $this->getProcessedFiles();
        
        // Шаг 4: Обрабатываем каждый файл
        $this->processEachFile();
        
        // Шаг 5: Удаляем временные файлы
        // $this->cleanup();
        
        return $this->processedFiles;
    }
    
    /**
     * Шаг 1: Скачиваем JSON во временный файл
     */
    private function downloadJsonToFile($url, $headers, $params) {
        // Формируем заголовки
        $headerStrings = [];
        foreach ($headers as $key => $value) {
            $headerStrings[] = "$key: $value";
        }
        
        // Скачиваем JSON сразу в файл
        $ch = curl_init();
        $fp = fopen($this->tempFile, 'w');
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api-ru.iiko.services/' . $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headerStrings,
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 30
        ]);
        
        curl_exec($ch);
        
        if (curl_error($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        fclose($fp);
        
        echo "JSON скачан во временный файл: " . $this->tempFile . "\n";
    }
    
    /**
     * Шаг 2: Извлекаем категории через jq
     */
	private function extractCategoriesWithJq() {
		// Просто извлекаем категории без дополнительных полей
		$command = sprintf(
			"jq -c '.itemCategories[]? | select(.isHidden | not)' %s",
			escapeshellarg($this->tempFile)
		);
		
		$descriptors = [
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];
		
		$process = proc_open($command, $descriptors, $pipes);
		
		if (!is_resource($process)) {
			throw new Exception("Не удалось запустить jq");
		}
		
		$index = 0;
		$this->processedFiles = [];
		
		while (($line = fgets($pipes[1])) !== false) {
			$line = trim($line);
			if (empty($line)) continue;
			
			$categoryData = json_decode($line, true);
			if ($categoryData && isset($categoryData['id'])) {
				// Используем ID из данных как имя файла
				$filename = $categoryData['id'];
				
				// На всякий случай очищаем ID (хотя UUID безопасен)
				$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
				
				$outputFile = sprintf("%s/%s.json", $this->outputDir, $safeFilename);
				
				file_put_contents(
					$outputFile,
					json_encode($categoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
				);
				
				$this->processedFiles[] = $outputFile;
				$index++;
			}
		}
		
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);
		
		echo "Всего создано файлов: " . count($this->processedFiles) . "\n";
	}
    
    /**
     * Шаг 3: Получаем список созданных файлов
     */
    private function getProcessedFiles() {
        // Альтернативный способ: просто прочитать директорию
        $files = glob($this->outputDir . '/*.json');
        
        // Убираем временный файл из списка
        $this->processedFiles = array_filter($files, function($file) {
            return $file !== $this->tempFile && basename($file) !== 'temp_response.json';
        });
        
        // Сортируем файлы по дате создания (новые сначала)
        usort($this->processedFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        echo "Найдено " . count($this->processedFiles) . " файлов категорий\n";
    }
    
    /**
     * Шаг 4: Обрабатываем каждый файл
     */
    private function processEachFile() {
        $totalFiles = count($this->processedFiles);
        $processed = 0;
        
        foreach ($this->processedFiles as $index => $filePath) {
            // Читаем и декодируем файл
            $jsonContent = file_get_contents($filePath);
            $categoryData = json_decode($jsonContent, true);
            
            if ($categoryData) {
                // ЗДЕСЬ ВАША ЛОГИКА ОБРАБОТКИ КАТЕГОРИИ
                // Например:
                $this->processCategory($categoryData, $filePath);
            }
            
            $processed++;
            
            // Прогресс каждые 100 файлов
            if ($processed % 100 == 0 || $processed == $totalFiles) {
                echo "Обработано $processed из $totalFiles файлов\n";
            }
            
            // Опционально: очищаем память
            unset($categoryData, $jsonContent);
            
            // Принудительная сборка мусора каждые 1000 файлов
            if ($processed % 1000 == 0) {
                gc_collect_cycles();
            }
        }
    }
    
    /**
     * Пример обработки категории
     * Замените этой своей логикой
     */
    private function processCategory($categoryData, $filePath) {
        // Пример: выводим информацию о категории
        if (isset($categoryData['id']) && isset($categoryData['name'])) {
            echo "<p>Обработка: " . $categoryData['name'] . "</p>";
            
            // Здесь ваша логика обработки
            // Например, запись в БД, отправка в другое API и т.д.
            
            // Сохраняем обработанный файл (если нужно)
            // file_put_contents($filePath . '.processed', 'ok');
        }
    }
    
    /**
     * Шаг 5: Очистка - удаляем все файлы
     */
    private function cleanup() {
        // Удаляем временный файл ответа
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
            echo "Удален временный файл\n";
        }
        
        // Удаляем все созданные файлы категорий
        $deleted = 0;
        foreach ($this->processedFiles as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
                $deleted++;
            }
        }
        
        echo "Удалено $deleted файлов категорий\n";
        
        // Удаляем директорию (опционально)
        // rmdir($this->outputDir);
    }
    
    /**
     * Получить список файлов без удаления
     */
    public function getFileList() {
        return $this->processedFiles;
    }
    
    /**
     * Обработать конкретный файл по пути
     */
    public function processSingleFile($filePath) {
        $jsonContent = file_get_contents($filePath);
        $categoryData = json_decode($jsonContent, true);
        
        if ($categoryData) {
            $this->processCategory($categoryData, $filePath);
            return true;
        }
        
        return false;
    }
}

?>