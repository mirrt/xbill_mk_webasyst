<?php

return array(
    
    'usr'               => array(
        'value'         => '',
        'title'         => 'Логин',   
        'description'   => 'Ваш логин на сайте x-bill.org',
        'control_type'  => waHtmlControl::INPUT,
    ),
    
   
    
     'key'               => array(
        'value'         => '',
        'title'         => 'Контрольная строка',   
        'description'   => 'Указана в настройках проекта',
        'control_type'  => waHtmlControl::INPUT,
    ),
    
    'sid'               => array(
        'value'         => '',
        'title'         => 'ID проекта',   
        'description'   => '',
        'control_type'  => waHtmlControl::INPUT,
    ),
    
    'desc'               => array(
        'value'         => '',
        'title'         => 'Описание платежа (от 10 до 100 символов)',   
        'description'   => '',
        'control_type'  => waHtmlControl::INPUT,
    ),
    
    'answer'               => array(
        'value'         => '',
        'title'         => 'Ответ покупателю после оплаты (по SMS) 10-140 символов',   
        'description'   => '',
        'control_type'  => waHtmlControl::INPUT,
    ),
    
    
);
