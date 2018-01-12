<?php
// Get post data
try {
	$requestBody = file_get_contents('php://input');
} catch (Exception $e) {
	
}

debug(json_encode($requestBody));

define('FACEBOOK_API_URL', 'https://graph.facebook.com/v2.11/me/messages');
define('PAGE_ACCESS_TOKEN', "<YOUR_PAGE_ACCESS_TOKEN>");
$VERIFY_TOKEN = "<YOUR_VERIFY_TOKEN>";

debug(" REQUEST BEFORE - ".json_encode($_REQUEST));

$parts = parse_url($_SERVER['REQUEST_URI']);
parse_str($parts['query'], $_REQUEST);

$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') ? true : false;

$mode = $_REQUEST['hub_mode'];
$token = $_REQUEST['hub_verify_token'];
$challenge = $_REQUEST['hub_challenge'];

debug(" REQUEST PARSED - ".json_encode($_REQUEST));
debug(" ISPOST: ".json_encode($isPost));

switch ($action) {
	
	default:
		
		if($isPost) {
			$event = array();
			$data = json_decode($requestBody, true);

			if(isset($data['entry'])) {
				debug("EVENT_RECEVEID");
				$event = $data['entry'][0]['messaging'][0];
				debug(" EVENT: ".json_encode($event));
				$sender_psid = $event['sender']['id'];

				// Check if the event is a message or postback and
				// pass the event to the appropriate handler function
				if (isset($event['message'])) {

					switch (strtolower(trim($event['message']['text']))) {
						case 'string_1':
							callSendAPI($sender_psid, array('text' => 'A case woth string_1'));
						break;

						case 'hi':
						case 'hello':
						case 'ciao':
						case 'salve':
						case 'buongiorno':
						case 'buonasera':
							callSendAPI($sender_psid, array('text' => 'Ciao! :D'));
						break;

						case 'info':
						case 'information':
						case 'informazioni':
							callSendAPI($sender_psid, array('text' => "YOUR INFO HERE ;)"));
						break;

						case 'settings':
						case 'setting':
						case 'impostazioni':
						case 'gestire':
							callSendAPI($sender_psid, array('text' => "YOUR SETTINGS AND COMMAND LIST HERE :)"));
						break;
						
						default:
							handleMessage($sender_psid, $event['message']);        
						break;
					}

				} elseif (isset($event['postback'])) {
					handlePostback($sender_psid, $event['postback']);
				}


			}else{
				debug("NO ENTRY KEY");
				// error
			}

		}elseif ($mode && $token) {

			// Checks the mode and token sent is correct
			if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
			  
			  // Responds with the challenge token from the request
			  debug("WEBHOOK_VERIFIED");
			  callSendAPI($sender_psid, array('text' => 'WELCOME!')); // did not show
			  $response = $challenge;

			} else {
			  // Responds with '403 Forbidden' if verify tokens do not match
			  $response = array('error' => 'forbidden', 'code' => 403);
			}
		}

		break;
}

if(isset($response['error'])) {
	header('location: HTTP/1.0 403 Forbidden');
	die();
}
echo $response;

exit();


function debug($data="") {
	file_put_contents("/tmp/facebook_bot.log", date('Y-m-d H:i:s')." - [INFO] - " . $data . "\n", FILE_APPEND);
}

// https://developers.facebook.com/docs/messenger-platform/getting-started/quick-start
function handleMessage($sender_psid, $received_message) {
	$response = array();

	// Check if the message contains text
	if (isset($received_message['text'])) {    
		// Create the payload for a basic text message
		$response = array(
			"text" =>  "Basic text message here :)"
		);
	} elseif (isset($received_message['attachments'])) {
		$attachment_url = $received_message['attachments'][0]['payload']['url'];
		$response = array(
			"attachment" => array(
				"type" => "template",
				"payload" => array(
					"template_type" => "generic",
					"elements" => array(
						array(
							"title" => "Is it an image?",
							"subtitle" => "Click on a button to confirm",
							"image_url" => $attachment_url,
							"buttons" => array(
							  array(
							    "type" => "postback",
							    "title" => "Yes",
							    "payload" => "yes",
							  ),
							  array(
							    "type" => "postback",
							    "title" => "No!",
							    "payload" => "no",
							  )
							),
						)
					)
				)
			)
		);
	}

	// Sends the response message
	callSendAPI($sender_psid, $response);  
}

function handlePostback($sender_psid, $received_postback) {
	$response = array();

	// Get the payload for the postback
	$payload = $received_postback['payload'];

	switch ($payload) {
		case 'yes':
			$response = array('text' => 'Thank you!');
		break;
		case 'no':
			$response = array('text' => 'Oops, try again!');	
		break;
		case 'subscribed':
			// Si è iscritto alla chat, tramite il get_started
			$response = array('text' => 'Thank you for subscribe to our bot!');
		break;
		
		default:
			$response = array('text' => 'wat? :|');
		break;
	}

	// Send the message to acknowledge the postback
	callSendAPI($sender_psid, $response);
}

function callSendAPI($sender_psid, $response) {
	// Construct the message body
	$request_body = array(
		"recipient" => array(
			"id" =>  $sender_psid
		),
		"message" => $response
	);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, FACEBOOK_API_URL."?access_token=".PAGE_ACCESS_TOKEN);
	curl_setopt($ch, CURLOPT_POST, 1);

	curl_setopt($ch, CURLOPT_POSTFIELDS, 
	       http_build_query($request_body));

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_output = curl_exec ($ch);
	curl_close ($ch);

	debug(" - RESPONE SEND API - " . json_encode($server_output));

}

?>
