<?php
namespace PedroSantiago\Nfse;

use DOMDocument;
use Illuminate\Support\Facades\Log;

class Api
{
	private $_nfse;
	private $_inputXml;
	private $_outputXml;

	public $version;
	public $certificate;
	public $privateKey;
	public $password;
	public $cnpj;
	public $inscricaoMunicipal;

	private $env;

	const NS = 'http://www.abrasf.org.br/nfse';

	public function __construct(array $nfse)
	{
		$this->_nfse = $nfse;
		$this->version = $nfse['version'];
		$this->certificate = $nfse['certificate'];
		$this->privateKey = $nfse['privateKey'];
		$this->password = $nfse['password'];
		$this->cnpj = $nfse['cnpj'];
		$this->env = isset($nfse['env']) ? $nfse['env'] : 'production' ;
		$this->inscricaoMunicipal = $nfse['inscricaoMunicipal'];
	}

	public function getInputXml(){
		return $this->_inputXml;
	}

	public function getOutputXml(){
		return $this->_outputXml;
	}

	protected function validate(DOMDocument $xml)
	{
	    return true;
		//return $xml->schemaValidate(dirname(__FILE__).'/../schemas/nfse.xsd');

		//If something goes wrong, throws an exception.
		/*$errors = libxml_get_errors();
		Yii::log('validation errors: '.print_r($errors,true),CLogger::LEVEL_ERROR,'ext.nfse.validate');
		libxml_clear_errors();
		return false;*/
	}

	protected function sign(DOMDocument $xml, $node, $id, $prefix='NfseAssPrestador_')
	{
		if($node->hasAttribute('Id'))
			$URI=$node->getAttribute('Id');
		else {
			$URI=$this->cnpj.$this->inscricaoMunicipal.date('YmdHi').sprintf('%02d',$id);
			$node->setAttribute('Id',$prefix.$URI);
		}

		$NS='http://www.w3.org/2000/09/xmldsig#';

		$signature=$xml->createElementNS($NS,'Signature');
		//$signature->setAttribute('Id',$prefix.$URI);
		$node->parentNode->appendChild($signature);

		$signedInfo=$xml->createElementNS($NS,'SignedInfo');
		$signature->appendChild($signedInfo);

		$canonicalizationMethod=$xml->createElementNS($NS,'CanonicalizationMethod');
		$canonicalizationMethod->setAttribute('Algorithm','http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
		$signedInfo->appendChild($canonicalizationMethod);

		$signatureMethod=$xml->createElementNS($NS,'SignatureMethod');
		$signatureMethod->setAttribute('Algorithm','http://www.w3.org/2000/09/xmldsig#rsa-sha1');
		$signedInfo->appendChild($signatureMethod);

		$reference=$xml->createElementNS($NS,'Reference');
		$reference->setAttribute('URI','#'.$prefix.$URI);
		$signedInfo->appendChild($reference);

		$transforms=$xml->createElementNS($NS,'Transforms');
		$reference->appendChild($transforms);

		$transform=$xml->createElementNS($NS,'Transform');
		$transform->setAttribute('Algorithm','http://www.w3.org/2000/09/xmldsig#enveloped-signature');
		$transforms->appendChild($transform);

        $transform=$xml->createElementNS($NS,'Transform');
        $transform->setAttribute('Algorithm','http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transform);

		$digestMethod=$xml->createElementNS($NS,'DigestMethod');
		$digestMethod->setAttribute('Algorithm','http://www.w3.org/2000/09/xmldsig#sha1');
		$reference->appendChild($digestMethod);

		$digest=base64_encode(sha1($node->C14N(),true));

		$digestValue=$xml->createElementNS($NS,'DigestValue',$digest);
		$reference->appendChild($digestValue);

		$pkeyid=openssl_get_privatekey('file://'.str_replace(DIRECTORY_SEPARATOR,'/',$this->privateKey),$this->password);
		if($pkeyid===false)
		{
			Log::error('Unable to open/parse/decrypt private key file');
			return false;
		}
		$res=openssl_sign($signedInfo->C14N(),$value,$pkeyid);
		openssl_free_key($pkeyid);
		if($res===false)
		{
			Log::error('signing failure');
			return false;
		}

		$signatureValue = base64_encode($value);

		$signatureValue = $xml->createElementNS($NS,'SignatureValue',$signatureValue);
		$signature->appendChild($signatureValue);

		$keyInfo = $xml->createElementNS($NS,'KeyInfo');
		$signature->appendChild($keyInfo);

		$x509Data = $xml->createElementNS($NS,'X509Data');
		$keyInfo->appendChild($x509Data);

		$certificate = file_get_contents($this->certificate);
		if($certificate === false) {
			throw new \Exception('Unable to open certificate file.');
		}

		$certificate = str_replace(array('-----BEGIN CERTIFICATE-----','-----END CERTIFICATE-----',"\n","\r","\t",' '),'',$certificate);

		$x509Certificate = $xml->createElementNS($NS,'X509Certificate',$certificate);
		$x509Data->appendChild($x509Certificate);
		return true;
	}

	protected function verify(DOMDocument $xml,DOMDocument $signature,$node=null)
	{
		$nodes= $signature->getElementsByTagName('SignedInfo');
		if($nodes->length!==1) {
			throw new \Exception('Signature element should have a single SignedInfo child');
		}

		$signedInfo=$nodes->item(0);

		$nodes = $signature->getElementsByTagName('SignatureValue');
		if($nodes->length!==1) {
			throw new \Exception('Signature element should have a single SignatureValue child');
		}
		$signatureValue=$nodes->item(0)->nodeValue;
		$signatureValue=str_replace(array("\n","\r","\t",' '),'',$signatureValue);

		$nodes = $signature->getElementsByTagName('X509Certificate');
		if($nodes->length!==1) {
			throw new \Exception('Signature element should have a single X509Certificate child');
		}

		$certificate=$nodes->item(0)->nodeValue;
		$certificate=str_replace(array("\n","\r","\t",' '),'',$certificate);
		$certificate=
			"-----BEGIN CERTIFICATE-----\n".
			chunk_split($certificate,64,"\n").
			"-----END CERTIFICATE-----\n";

		$value=base64_decode($signatureValue);
		if($value===false) {
			throw new \Exception('Unable to decode the signature value: '.$signatureValue);
		}

		$pkeyid = openssl_get_publickey($certificate);
		if($pkeyid===false) {
			throw new \Exception('Unable to open/parse certificate: '.$certificate);
		}

		$res = openssl_verify($signedInfo->C14N(),$value,$pkeyid);
		openssl_free_key($pkeyid);
		if($res!==1) {
			throw new \Exception('Verification failure');
		}

		$nodes = $signedInfo->getElementsByTagName('Reference');
		if($nodes->length!==1) {
			throw new \Exception('SignedInfo element should have a single Reference child');
		}

		$reference = $nodes->item(0);
		if(!$reference->hasAttribute('URI')) {
			throw new \Exception('Reference element should have an URI attribute');
		}

		$URI = $reference->getAttribute('URI');
		if(substr($URI,0,1)!=='#') {
			throw new \Exception('Reference URI should be relative to current document');
		}

		$URI = substr($URI,1);

		if($node === null) {
			$xpath= new \DOMXPath($xml);
			$nodes=$xpath->query("//*[@Id='$URI']");

			if($nodes->length !== 1) {
				throw new \Exception('Reference URI should have a single target');
			}

			$node = $nodes->item(0);
		} else {
			$id=$node->getAttribute('Id');
			if($id!==$URI) {
				throw new \Exception('Reference URI does not match target ID');
			}
		}

		$nodes=$reference->getElementsByTagName('DigestValue');
		if($nodes->length!==1) {
			throw new \Exception('Reference element should have a single DigestValue child');
		}

		$digest1 = $nodes->item(0)->nodeValue;
		$digest1 = str_replace(array("\n","\r","\t",' '),'',$digest1);
		$digest2 = base64_encode(sha1($node->C14N(),true));
		if($digest1 !== $digest2) {
			throw new \Exception('Message digest mismatch');
		}

		return true;
	}

	protected function signAll($xml,$NS,$tags2sign)
	{
		$signcount=0;
		foreach($tags2sign as $key=>$tag2sign)
		{
			$nodes=$xml->getElementsByTagNameNS($NS,$tag2sign);
			for($i=0; $i<$nodes->length; $i++)
			{
				$node=$nodes->item($i);
				$result=$this->sign($xml,$node,++$signcount);
				if($result===false) return false;
			}
		}
		return true;
	}

	protected function processHeader($validate=true)
	{
		/*$xml = new DOMDocument('1.0','UTF-8');
		$cabecalho = $xml->createElement('cabecalho');
		$cabecalho->setAttribute('versao',$this->version);
		$xml->appendChild($cabecalho);

		$versaoDados=$xml->createElementNS(self::NS, 'versaoDados', $this->version);
		$cabecalho->appendChild($versaoDados);
		if($validate) {
			if(!$this->validate($xml)) {
				throw new \Exception('Header validation failure: '.$xml->saveXML());
			}
		}

		$xmlString = $xml->saveXML();
		return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlString); */

        return '';
	}

	protected function processInput($xmlservice,$object,$tags2sign,$validate=true)
	{
		$xml = new DOMDocument('1.0','UTF-8');

		$envio = $xml->createElementNS(self::NS,$xmlservice.'Envio');
		$xml->appendChild($envio);

		$this->encode($object, $xml,self::NS, $envio);

		$result = $this->signAll($xml,self::NS,$tags2sign);

		if($result===false) {
			return false;
		}

		if($validate) {
			if(!$this->validate($xml)) {
				throw new \Exception('Input validation failure: '.$xml->saveXML());
			}
		}

		$this->_inputXml = $xml->saveXML();
		return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->_inputXml);
	}

	protected function fold($service, $header, $input)
	{
		$xml = new DOMDocument('1.0','UTF-8');

		$envelope = $xml->createElementNS('http://schemas.xmlsoap.org/soap/envelope/','soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
		$xml->appendChild($envelope);

		$body=$xml->createElement('soapenv:Body');
		$envelope->appendChild($body);

        $ns2=$xml->createElementNS('http://tempuri.org/', $service);
		$body->appendChild($ns2);

		$nfseCabecMsg=$xml->createElement('cabec',$header);

		$ns2->appendChild($nfseCabecMsg);

		$nfseDadosMsg=$xml->createElement('msg',$input);
		$ns2->appendChild($nfseDadosMsg);

		return $xml->saveXML();
	}

	protected function unfold($service, $response)
	{
        $response = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $response);
        $xmlresponse = @DOMDocument::loadXML($response, LIBXML_NOBLANKS);

		if($xmlresponse===false){
            return false;
        }
		$envelope=$xmlresponse->documentElement;
		if($envelope->tagName!=='s:Envelope'){
            return false;
        }
		if($envelope->namespaceURI!=='http://schemas.xmlsoap.org/soap/envelope/'){
            return false;
        }
		if($envelope->childNodes->length!==1){
            return false;
        }
		$body=$envelope->childNodes->item(0);
		if($body->tagName!=='s:Body'){
            return false;
        }
		if($body->childNodes->length!==1){
            return false;
        }
		$ns2=$body->childNodes->item(0);
		if($ns2->tagName!==$service.'Response'){
            return false;
        }
		if($ns2->namespaceURI!=='http://tempuri.org/'){
            return false;
        }
		if($ns2->childNodes->length!==1){
            return false;
        }
		$outputXML=$ns2->childNodes->item(0);
		if($outputXML->tagName!==$service.'Result'){
            return false;
        }

		return $outputXML->nodeValue;
	}

	protected function processOutput($xmlservice,$output,$validate=true)
	{
	    $xml = @DOMDocument::loadXML($output);
		if($xml===false) {
			return false;
		}

		$this->_outputXml=$xml->saveXML();
		if($validate) {
			if(!$this->validate($xml)) {
				throw new \Exception('Output validation failure: '.$xml->saveXML());
			}
		}
		$resposta=$xml->documentElement;
		if($resposta->tagName !== $xmlservice.'Resposta'){
            return false;
        }
		if($resposta->namespaceURI !== self::NS){
            return false;
        }

        $output = $this->removerAcentos($output);
        $simpleXml = simplexml_load_string($output);
        $xmlJson = json_encode($simpleXml);
        return json_decode($xmlJson);
	}


    function value_in($element_name, $xml, $content_only = true) {
        if ($xml == false) {
            return false;
        }
        $found = preg_match('#<'.$element_name.'(?:\s+[^>]+)?>(.*?)'.
            '</'.$element_name.'>#s', $xml, $matches);
        if ($found != false) {
            if ($content_only) {
                return $matches[1];  //ignore the enclosing tags
            } else {
                return $matches[0];  //return the full pattern match
            }
        }
        // No match found: return false.
        return false;
    }


	protected function call($service,$object,$xmlservice='',$tags2sign=array())
	{
		if(empty($xmlservice)) $xmlservice=$service;

		$ch=curl_init();
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_HEADER,false);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_VERBOSE,false);

		$url='https://feiradesantanaba.webiss.com.br/servicos/wsnfse/NfseServices.svc?wsdl';
		if($this->env !== 'production') {
			$url = str_replace('wsnfse', 'wsnfse_homolog', $url);
		}
		curl_setopt($ch,CURLOPT_URL,$url);

		$chain='bhissdigital.pbh.gov.br.chain.crt';
		if($this->env !== 'production'){
			$chain=str_replace('bhissdigital.pbh.gov.br','bhisshomologa.pbh.gov.br',$chain);
		}

		//Gambiarra
		$trustAllConnection = true;

		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,$trustAllConnection?0:2);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,!$trustAllConnection);
		if(!$trustAllConnection){
			curl_setopt($ch,CURLOPT_CAINFO,dirname(__FILE__).'/../certs/'.$chain);
		}

		curl_setopt($ch,CURLOPT_SSLCERT,$this->certificate); // "client.pem"
		curl_setopt($ch,CURLOPT_SSLCERTPASSWD,$this->password); // "s3cret"
		curl_setopt($ch,CURLOPT_SSLKEY,$this->privateKey);  // "key.pem"

		//if($nfse->useProxy) curl_setopt($ch,CURLOPT_PROXY,$nfse->proxyHost.':'.$nfse->proxyPort);

		$header= $this->processHeader($validate = true);
		if($header===false) {
			return false;
		}

		$input=$this->processInput($xmlservice,$object,$tags2sign,$validate = true);
		if($input===false) {
			return false;
		}

		$request=$this->fold($service,$header,$input);
		if($request===false){
			return false;
		}

        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"http://tempuri.org/INfseServices/$service\"",
            "Content-length: ".strlen($request),
        );

        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$request);

		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if($errno!==0)
		{
			Log::error('cURL error: ('.$errno.') '.$error.' '.$this->_inputXml);
			return false;
		}
		if($httpcode!==200)
		{
            Log::error('HTTP error: ('.$httpcode.') '.$error.' '.$response.' '.$this->_inputXml);
			return false;
		}

		$output=$this->unfold($service, $response);
		if($output===false)
		{
            Log::error('failure unfolding response '.$this->_inputXml);
			return false;
		}
		$object=$this->processOutput($xmlservice,$output, false);
		if($object===false)
		{
            Log::error('failure processing output '.$this->_inputXml.' '.$this->_outputXml);
			return false;
		}

		return $object;
	}

	protected function encode($request,$xml,$NS,$node)
	{
		if($request===null)
		{
			$this->encode('',$xml,$NS,$node);
			return;
		}
		if(is_string($request))
		{
			$node->appendChild(new \DOMText($request));
			return;
		}
		if(is_bool($request))
		{
			$this->encode($request?'true':'false',$xml,$NS,$node);
			return;
		}
		if(is_object($request))
		{
			$this->encode(get_object_vars($request),$xml,$NS,$node);
			return;
		}
		if(is_array($request))
		{
			foreach($request as $key=>$value)
			{
				$chld=$xml->createElementNS($NS,$key);
				$this->encode($value,$xml,$NS,$chld);
				$node->appendChild($chld);
			}
			return;
		}
		$this->encode((string)$request,$xml,$NS,$node);
	}

	protected function decode($node)
	{
		$object=new \stdClass;
		$children=$node->childNodes;

		for($i=0; $i<$children->length; $i++)
		{
			$child=$children->item($i);
			if($child instanceof \DOMText) {
				if($children->length!==1){
					return false;
				}
				return $node->nodeValue;
			}
			$key=$child->tagName;
			$object->$key=$this->decode($child);
		}
		return $object;
	}

	public function parseXml($input)
	{
		$xml= DOMDocument::loadXML($input);
		if($xml===false){
			return false;
		}

		return $this->decode($xml->documentElement);
	}

    private function removerAcentos($string)
    {
        return preg_replace(array("/(á|à|ã|â|ä)/","/(Á|À|Ã|Â|Ä)/","/(é|è|ê|ë)/","/(É|È|Ê|Ë)/","/(í|ì|î|ï)/","/(Í|Ì|Î|Ï)/","/(ó|ò|õ|ô|ö)/","/(Ó|Ò|Õ|Ô|Ö)/","/(ú|ù|û|ü)/","/(Ú|Ù|Û|Ü)/","/(ñ)/","/(Ñ)/","/(ç)/","/(Ç)/"), explode(" ","a A e E i I o O u U n N c C"), $string);
    }

	public function CancelarNfse($request)
	{
		return $this->call(__FUNCTION__,$request,'',array('InfPedidoCancelamento'));
	}

	public function ConsultarLoteRps($request)
	{
		return $this->call(__FUNCTION__,$request,'',array());
	}

	public function ConsultarNfse($request)
	{
		return $this->call(__FUNCTION__,$request,'',array());
	}

	public function ConsultarNfsePorFaixa($request)
	{
		return $this->call(__FUNCTION__,$request,'ConsultarNfseFaixa',array());
	}

	public function ConsultarNfsePorRps($request)
	{
		return $this->call(__FUNCTION__,$request,'ConsultarNfseRps',array());
	}

	public function ConsultarSituacaoLoteRps($request)
	{
		return $this->call(__FUNCTION__,$request);
	}

    public function GerarNfse($request)
    {
        return $this->call(__FUNCTION__,$request,'',array('InfRps','LoteRps'));
    }

	public function RecepcionarLoteRps($request)
	{
		return $this->call(__FUNCTION__,$request,'EnviarLoteRps',array('InfRps', 'LoteRps'));
	}
}