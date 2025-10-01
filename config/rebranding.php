<?php

return [
    'subject'    => 'IM Ingeniería: nuevo logo + encuesta de satisfacción',
    'survey_url' => env('REBRANDING_SURVEY_URL', 'https://cim.ienm.com.co/encuesta/'),
    'from_email' => env('MAIL_FROM_ADDRESS', 'no-reply@ienm.com.co'),
    'from_name'  => env('MAIL_FROM_NAME', 'IM Ingeniería'),
];
