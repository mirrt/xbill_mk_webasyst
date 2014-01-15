<?php

/**
 * @see http://x-bill.ru
 * @see http://x-bill.org
 */

class xbillPayment extends waPayment implements waIPayment
{
    private $mk_config = array();

    public function allowedCurrency()
    {
        return $this->merchant_currency ? $this->merchant_currency : 'RUB';
    }
   
    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
        $post = waRequest::post();
        $form_sended = FALSE;
        $form_message = '';
        
        $order = waOrder::factory($order_data);
     
        if(isset($post['phone']) and (float) $post['mix'] > 0)
        {
            $form_sended = TRUE;
            $this->set_config();
            
            $params_for_back = array(
                'order_id'               => $order->id,
                'order_sum'              => $order->total,
                'wa_app'                 => $this->app_id,
                'wa_merchant_contact_id' => $this->merchant_id,
            );
            
            $payment_mk = $this->mk_create_pay (
                                    $post['phone'], 
                                    $post['mix'], 
                                    $this->mk_config['desc'], 
                                    $this->mk_config['answer'],
                                    $params_for_back  
                          );
           
            if($payment_mk['status'] == 0)
            {
                $form_message = 'На Ваш номер телефона отправлена SMS, после подтверждения платежа, счет будет оплачен моментально.';
            }
            else
            {
                $form_message = 'При проведении платежа возникла следующая ошибка: <br>';
                $form_message .= $payment_mk['status'] . '-' . $payment_mk['status_desc'];
            }       
        } 
        
        $pay_sum = number_format($order->total, 2, '.', '');
        
        $file_path = substr($_SERVER['PHP_SELF'], 0, -10).'/wa-plugins/payment/xbill';
        
        $view = wa()->getView();
        
        $view->assign('form_message', $form_message);
        $view->assign('pay_sum', $pay_sum);
        $view->assign('form_sended', $form_sended);
        $view->assign('file_path', $file_path);

        return $view->fetch($this->path.'/templates/payment.html');
    }

    protected function callbackInit($request)
    {
        if (!empty($request['order_id']) && !empty($request['wa_app']) && !empty($request['wa_merchant_contact_id'])) {
            $this->app_id = $request['wa_app'];
            $this->merchant_id = $request['wa_merchant_contact_id'];
            $this->order_id = $request['order_id'];
            $this->order_sum = $request['order_sum'];
        } else {
            self::log($this->id, array('error' => 'empty required field(s)'));
            throw new waException('Empty required field(s)');
        }
        return parent::callbackInit($request);
    }

    
    public function callbackHandler($request)
    {
        $transaction_data = $this->formalizeData($request);

        $this->set_config();
        
        /*
        if (!$this->verifySign($request)) 
        {
            throw new waPaymentException('invalid signature', 404);
        }
        */
        
        if ($transaction_data['amount'] / $this->order_sum < 0.8)
        {
            throw new waPaymentException('invalid payment sum', 404);
        }
            
        $transaction_data['state'] = self::STATE_CAPTURED;

        $transaction_data = $this->saveTransaction($transaction_data, $request);

        $result = @$this->execAppCallback(self::CALLBACK_PAYMENT, $transaction_data);

        self::addTransactionData($transaction_data['id'], $result);
        
        if($transaction_data['order_status'] == 'success')
            echo 'ok';
        else 
            echo 'error';
        
        exit;        
    }

    protected function formalizeData($transaction_raw_data)
    {
        $transaction_data = parent::formalizeData($transaction_raw_data);
        $transaction_data['native_id'] = $this->order_id;
        $transaction_data['order_id'] = $this->order_id;
        $transaction_data['amount'] = ifempty($transaction_raw_data['paytouser'], '');
        $transaction_data['order_status'] = ifempty($transaction_raw_data['order_status'], '');
        $transaction_data['currency_id'] = $this->merchant_currency;
        return $transaction_data;
    }


    private function verifySign($request)
    {
        $sign = ifset($request['signature']);
         
        return $sign == md5($request['order'].
                            $request['phone'].
                            $request['merchant_price'].
                            $this->mk_config['key']);
    }
    
   
    private function set_config()
    {
        // Общие настройки, заданные в админке
        $this->mk_config = $this->getSettings(); 
        
        // URL, на которые будем отправлять запросы
        $this->mk_config['api1'] = 'http://api.x-bill.org/';
        $this->mk_config['api2'] = 'http://api.x-bill.ru/';
    }
    
    
    private function mk_create_pay ($phone, $cost, $desc, $answer="", $arr=array()) 
    {
	$phone = preg_replace('/[^0-9]/', '', $phone);
	$cost = (float)str_replace(",", ".", $cost);
	$desc = $desc;
	$answer = $answer;
	$var = "";
        
	if (isset ($arr)) 
        {
            $keys = array_keys ($arr);
            for($i=0; $i<count($keys); $i++){ $var .= "&{$keys[$i]}=".$arr[$keys[$i]]; }
	}
        
	$post = "phone={$phone}&cost={$cost}&desc={$desc}&answer={$answer}&sign=".$this->mk_create_sign($phone)."&login={$this->mk_config['usr']}&sid={$this->mk_config['sid']}{$var}";

	$result = $this->mk_send_data ($post, $this->mk_config['api1']."payment.php");
        
	if ($result == 'error') 
        { 
            $result = mk_send_data ($post, $this->mk_config['api2']."payment.php"); 
        }
	
        if ($result == 'error') 
            return "0";
	else
        {
            $result = $this->mk_parse_result($result);
            return $result;
	}
    }
    
    private function mk_create_sign ($phone="")
    {
	return md5( $this->mk_config['usr'] .
                    $this->mk_config['key'] .
                    $this->mk_config['sid'] .
                    $phone);
    }
    
    
    function mk_parse_result ($result)
    {
	$XML = trim($result);
	$returnVal = $XML;
	$emptyTag = '<(.*)/>';
	$fullTag = '<\\1></\\1>';
	$XML = preg_replace ("|$emptyTag|", $fullTag, $XML);
        
	$matches = array();
	
        if (preg_match_all('|<(.*)>(.*)</\\1>|Ums', trim($XML), $matches)) 
        {
            if (count($matches[1]) > 0) $returnVal = array();
            foreach ($matches[1] as $index => $outerXML)
            {
                $attribute = $outerXML;
                $value = $this->mk_parse_result($matches[2][$index]);
                if (! isset($returnVal[$attribute])) $returnVal[$attribute] = array();
                $returnVal[$attribute][] = $value;
            }
	}
        
	if (is_array($returnVal))
        {    
            foreach ($returnVal as $key => $value)
            { 
                if (is_array($value) && count($value) == 1 && key($value) === 0)
                {
                    $returnVal[$key] = $returnVal[$key][0]; 
                } 
            }
        }
        
	return $returnVal;	
    }
    
    
    private function mk_send_data ($post, $url)
    {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 4);  
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	
	$result = curl_exec($ch);
        
	$status = curl_errno($ch);   

	curl_close($ch);   
        
	if ($status == 0 && !empty($result)) 
            return $result; 
        else
            return "error"; 
     }
    
    
}
