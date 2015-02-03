<?php

	error_reporting(-1);
	ini_set('display_errors', 'On');
	
	require 'flight/Flight.php';
	require 'flight/helpers.php';

	// app settings
	Flight::set('secret_key', '76sdf65sd5f45675sd4f7svd75');
	Flight::set('domain', 'http://example.com');

	// db settings
	$link = mysqli_connect("localhost", "user","password","dbase") or die("DB is down");
	mysqli_set_charset($link, "utf8");

	// Test
	Flight::route('/', function() use ($link) {

		//$sql = 'SELECT * FROM SESSIONS';
		//$sql = 'SELECT * FROM USER';
		//$sql = 'SELECT * FROM PURCHASES';
    	//$raw_res = mysqli_query($link, $sql);
    	//var_dump(parse_result($raw_res));

	});

	// 404
	Flight::map('notFound', function(){
    	Flight::json(array("error" => "Метод не используется"));
    	//$request = Flight::request();
    	//var_dump($request);

	});


	// register
	Flight::route('/register/', function() use ($link) {
		
		$secret_key = Flight::get('secret_key');

		if (isset($_GET['email']) AND isset($_GET['password'])) {
			
			// prepare variables for request
			$password = get_crypt_password($_GET['password'], $secret_key);
			$email = $_GET['email'];
			$date = time();

			// save user's data
			$sql_user_data = "INSERT INTO USER (email, password, date) VALUES ('$email', '$password', CURRENT_TIMESTAMP)";
			$rq_user_data = mysqli_query($link, $sql_user_data);

			// If user's data successfully saved
			if ($rq_user_data == true) {

				// Get id of last insert
				$last_id = mysqli_insert_id($link);

				// Generate auth token for app
				$token = get_auth_token($date, $secret_key);

				// Save token to store with user id
				$sql_auth_token = "INSERT INTO SESSIONS (name, user_id) VALUES ('$token', '$last_id')";
				$rq_auth_token = mysqli_query($link, $sql_auth_token);

				// 
				if ($rq_auth_token == true) {
					
					// Return token to app
					Flight::json(array("OK" => $token));
				}

			} else {
				// If email not unique
				Flight::json(array("error" => "Указанный email уже используется"));
			}

		} else {
			// if password or email not given
			Flight::json(array("error" => "Не указан один из обязательных параметров"));
		}

	});


	// login
	Flight::route('/login/', function() use ($link) {

		if (isset($_GET['email']) AND isset($_GET['password'])) {
			
			// prepare variables for request
			$secret_key = Flight::get('secret_key');
			$email = $_GET['email'];
			$password = get_crypt_password($_GET['password'], $secret_key);

			// Get user by email
			$sql_select_by_email = "SELECT * FROM USER WHERE email = '$email'";
    		$rq_select_by_email = mysqli_query($link, $sql_select_by_email);
    		$user = parse_result($rq_select_by_email);

    		// If email not found
    		if (empty($user)) {
    			Flight::json(array("error" => "Пользователь с таким email не найден"));

    		// If email found
    		} else if ($user[0]['password'] == $password) {

    			// get user auth token
    			$user_id = $user[0]['id'];
    			$sql_get_token = "SELECT name FROM SESSIONS WHERE user_id = '$user_id'";
    			$rq_get_token = mysqli_query($link, $sql_get_token);
    			$founded_token = parse_result($rq_get_token);

    			Flight::json(array("OK" => $founded_token[0]['name']));

    		} else {

    			Flight::json(array("error" => "Пароль или email не указаны"));
    		}

		}
	});


	// recovery password
	Flight::route('/recovery/', function() use ($link) {
		if (isset($_GET['email'])) {

			// prepare variables for request
			$email = $_GET['email'];
			$secret_key = Flight::get('secret_key');
			$date = time();

			$sql_find_by_email = "SELECT id, email FROM USER WHERE email = '$email' LIMIT 1";
			$rq_find_by_email = mysqli_query($link, $sql_find_by_email);
			$user = parse_result($rq_find_by_email);
			
			$user_id = $user[0]['id'];
			$user_mail = $user[0]['email'];

			// if user found			
			if (!empty($user_id)) {

				$new_password = get_new_password();
				$new_hash = get_crypt_password($new_password, $secret_key);

				$sql_save_new_password = "UPDATE USER SET password='$new_hash' WHERE id='$user_id'";
				$rq_save_new_password = mysqli_query($link, $sql_save_new_password);

				if ($rq_save_new_password) {
					send_new_pass($user_mail, $new_password);
					Flight::json(array("OK" => "Пароль отправлен на почту"));
				}

			} else {
				// if user not found
				Flight::json(array("error" => "Пользователь с указанным email не найден"));
			}

		} else {
			// if email not set
			Flight::json(array("error" => "Не указан email"));
		}
	});

	// add purchase
	Flight::route('/purchases/add/', function() use ($link) {
		if (isset($_GET['token'])) {

			// prepare variables for request
			$token = $_GET['token'];
			$book_id = $_GET['book_id'];

			// get user id by token
			$sql_find_by_token = "SELECT id FROM SESSIONS WHERE name = '$token' LIMIT 1";
			$rq_find_by_token = mysqli_query($link, $sql_find_by_token);
			$user = parse_result($rq_find_by_token);
			$user_id = $user[0]['id'];

			// add purchased item
			$sql_add_purchased_item = "INSERT INTO PURCHASES (book_id, user_id, date) VALUES ('$book_id', '$user_id', CURRENT_TIMESTAMP)";
			$rq_add_purchased_item = mysqli_query($link, $sql_add_purchased_item);

			// get id of last inserted record
			$last_id = mysqli_insert_id($link);

			//if record successfully inserted
			if($rq_add_purchased_item) {
				Flight::json(array("OK" => $last_id));
			}

		} else {

			// if need authorization token
			Flight::json(array("error" => "Необходима авторизация"));
		}
	});

	// get all purchases
	Flight::route('/purchases/', function() use ($link) {
		if (isset($_GET['token'])) {

			// prepare variables for request
			$token = $_GET['token'];

			// get user id by token
			$sql_find_by_token = "SELECT id FROM SESSIONS WHERE name = '$token' LIMIT 1";
			$rq_find_by_token = mysqli_query($link, $sql_find_by_token);
			$user = parse_result($rq_find_by_token);
			$user_id = $user[0]['id'];

			// get purchases by user_id
			$sql_find_by_user_id = "SELECT book_id, date FROM PURCHASES WHERE user_id = '$user_id'";
			$rq_find_by_user_id = mysqli_query($link, $sql_find_by_user_id);
			$purchases = parse_result($rq_find_by_user_id);

			Flight::json($purchases);

		} else {
			// if need authorization token
			Flight::json(array("error" => "Необходима авторизация"));
		}
	});

	Flight::start();

?>