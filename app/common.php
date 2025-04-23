<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use chillerlan\QRCode\{QRCode, QROptions};

//       _______ ____  _   ______
//       / / ___// __ \/ | / / __ \
//  __  / /\__ \/ / / /  |/ / /_/ /
// / /_/ /___/ / /_/ / /|  / ____/
// \____//____/\____/_/ |_/_/

function __errorjsonp($msg='unknown'): never{
	$msg = !$msg?"unknown":$msg;
	global $callback;
	echo $callback.'('.json_encode(["error"=>$msg], JSON_UNESCAPED_UNICODE).')';
	glogError($msg);
	exit();
}	

function __answerjsonp($data): never{
	global $callback;
	echo $callback.'('.json_encode($data, JSON_UNESCAPED_UNICODE).')';
	exit();
}	

//       _______ ____  _   __
//       / / ___// __ \/ | / /
//  __  / /\__ \/ / / /  |/ /
// / /_/ /___/ / /_/ / /|  /
// \____//____/\____/_/ |_/

function __answerjson($data): never{	
	echo json_encode($data, JSON_UNESCAPED_UNICODE);
	exit();
}	
function __errorjson($msg): never{	
	echo json_encode(["error"=>$msg], JSON_UNESCAPED_UNICODE);
	exit();
}
	

function glogIikoHook($msg){	
	global $CFG;		
	$pre = "iikoHook: ";	
	$path = $CFG->dirroot.$CFG->log_path.$CFG->log_iiko_hook_file;		
	if (!file_exists($path)) { touch($path); }
	$log = date('Y-m-d H:i:s') . " $pre $msg";	
	file_put_contents($path, $log . PHP_EOL, FILE_APPEND);
}	

function glog($msg, $pre="log: "){	
	global $CFG;			
	$path = $CFG->dirroot.$CFG->log_path.$CFG->log_menu_file;
	if (!file_exists($path)) { touch($path); }
	$log = date('Y-m-d H:i:s') . " $pre $msg";	
	file_put_contents($path, $log . PHP_EOL, FILE_APPEND);
}	

function glogError($msg){	
	glog($msg, "gError: ");
}	

/* --------------------------------------------------

	          TG FUNCTIONS 

----------------------------------------------------- */

function send_telegram($method, $data, $token, $headers = []){
	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_POST => 1,
		CURLOPT_HEADER => 0,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/' . $method,
		CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
		CURLOPT_HTTPHEADER => array_merge(["Content-Type: application/json"])
	]);
	$result = curl_exec($curl);
	curl_close($curl);
	return (json_decode($result, true) ?: $result);
}

/* --------------------------------------------------

	          IIKO FUNCTIONS 

----------------------------------------------------- */

function iiko_get_info($url,$headers,$params){        
      $header = [];
      if (is_array($headers)) {
          $i_header = $headers;          
          foreach ($i_header as $param => $value) {
              $header[] = "$param: $value";
          }
      }      
      $curl = curl_init();
      curl_setopt_array($curl, [CURLOPT_URL => 'https://api-ru.iiko.services/'.$url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 15, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE), CURLOPT_HTTPHEADER => $header]);
      $json = curl_exec($curl);        
      curl_close($curl);

      // glog($json);
            
      return json_decode($json, true);
}

function iiko_tables_res_parse(array $restaurantSections): array{
	$arr = [];	
	if(is_array($restaurantSections) && count($restaurantSections)){
		foreach($restaurantSections as $section){
			array_push($arr,[
				'section_name'=>$section['name'],
				'section_id'=>$section['id'],
				'terminalGroupId'=>$section['terminalGroupId'],
				'tables'=>$section['tables']
			]);			
		}
	}
	return $arr;
}


/* --------------------------------------------------

	          QR-CODE FUNCTIONS 

----------------------------------------------------- */
// return qecode as imagedata or imagesource;
function rds_qrcode_create_from($link="http://chefsmenu.ru/unknown", $asImageData = false ){		
	$options = new QROptions([
		'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
		'returnResource'=> true,
		'scale' => 30,
		'quietzoneSize'=> 1,
	]);
	$im = (new QRCode($options))->render($link);
	ob_start();		
	imagepng($im);
	$imagedata = ob_get_contents();
	ob_end_clean();
	if($asImageData){		
		return $imagedata;
	}else{
		return 'data:image/png;base64,'.base64_encode($imagedata);		
	}
}


/* --------------------------------------------------

	          DATE / TIME FUNCTIONS 

----------------------------------------------------- */


function CHEFS__beautiful_date($date,$lang="ru"){
	if($lang=="ru"){
		$monthes = [1 => 'Января', 2 => 'Февраля', 3 => 'Марта', 4 => 'Апреля', 5 => 'Мая', 6 => 'Июня', 7 => 'Июля', 8 => 'Августа', 9 => 'Сентября', 10 => 'Октября', 11 => 'Ноября', 12 => 'Декабря'];
	}else{
		$monthes = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
	}
	$d = new Datetime($date);
	return $d->format('d ') . $monthes[($d->format('n'))] . $d->format(' Y');
}	

function glb_russian_datetime($str_time,$time_format=24){
	if(!$str_time) return "";		
	$date=explode(".", date("d.m",strtotime((string) $str_time)));
	$time = $time_format==24?date("H:i",strtotime((string) $str_time)):date("h:i A",strtotime((string) $str_time));
	$day = date("w",strtotime((string) $str_time));
	$arrMounth = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
	$m = $arrMounth[$date[1]-1];
	return $date[0].' '.$m.", ".$time;
}

function glb_russian_datetime_full($str_time,$time_format=24){
	if(!$str_time) return "";		
	$date=explode(".", date("d.m.Y",strtotime((string) $str_time)));
	$time = $time_format==24?date("H:i",strtotime((string) $str_time)):date("h:i A",strtotime((string) $str_time));
	$arrMounth = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
	$m = $arrMounth[$date[1]-1];
	return $date[0].' '.$m.'&nbsp;'.$date[2].", ".$time;
}

/* --------------------------------------------------

	          COMMON FUNCTIONS 

----------------------------------------------------- */

function go($href): never{
	@Header("Location: $href");exit();
}

function post_clean($var,$maxsize=0){	
	$var = trim(stripslashes(htmlspecialchars((string) $var)));
	if(empty($var)) return "";
	$var = str_replace("'","`",$var);
	return $maxsize? substr($var, 0, intval($maxsize)):$var;
}

function checkemail($email){
	return preg_match("|^[0-9a-z_\.]+@[0-9a-z_^\.]+\.[a-z]{2,6}$|i", (string) $email);
}	

function check_string($str){
	return preg_match("/^[- '\p{L}\s]+$/u", (string) $str);
}	

function get_random_string($length=16){
	$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
	return substr(str_shuffle($permitted_chars), 0, $length);
}	

function generate_password($max=6){
	$chars="1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
	$max = intval($max);
	$size=StrLen($chars)-1;
	$password=null;
	while($max--) 
	$password.=$chars[random_int(0,$size)]; 
	return $password;
}


/* SOME PUBLIC COMMON FUNCTIONS */

if(!function_exists('str_contains')){
	function str_contains(string $haystack, string $needle): bool {
	    return '' === $needle || str_contains($haystack, $needle);
	}
}


/**
 * ----------------------
 * склонение слова минут
 * в словосочетании
 * "ждет N минут",
 * где n = 0....60 
 * ----------------------
 */
function getMinutesWord(int $n): string {
    // Остатки от деления на 10 и 100
    $lastDigit = $n % 10; 
    $lastTwoDigits = $n % 100;

    // Проверяем исключения для 11–19
    if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
        return 'минут';
    }

    // Проверяем окончание числа
    if ($lastDigit === 1) {
        return 'минута';
    } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
        return 'минуты';
    } else {
        return 'минут';
    }
}

function saveArrayToUniqueJson(array $data, string $directory = 'storage'): ?string {

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
		
?>