<?php namespace NFse\Config;

use Exception;
use NFse\Models\Settings;

class WebService
{
    public $env;
    public $wsdl = null;
    public $folder = null;
    public $soapVersion = SOAP_1_1;
    public $connectionTimeout = 10;
    public $exceptions = true;
    public $trace = true;
    public $use = SOAP_LITERAL;
    public $style = SOAP_DOCUMENT;
    public $cacheWsdl = WSDL_CACHE_NONE;
    public $compression = 0;
    public $sslVerifyPeer = false;
    public $sslVerifyPeerName = false;

    public $urlHomolagation = [
        3106200 => 'https://bhisshomologa.pbh.gov.br/bhiss-ws/nfse?wsdl',
        3543402 => 'https://nfse.issnetonline.com.br/abrasf204/ribeiraopreto/nfse.asmx?wsdl',
        3147105 => 'https://parademinas.quasar.srv.br/nfe/'
    ];

    public $urlProduction = [
        3106200 => 'https://bhissdigital.pbh.gov.br/bhiss-ws/nfse?wsdl',
        3543402 => 'https://nfse.issnetonline.com.br/abrasf204/ribeiraopreto/nfse.asmx?wsdl',
        3147105 => 'https://parademinas.quasar.srv.br/nfe/'
    ];

    /**
     * construtor
     *
     * @param NFse\Models\Settings;
     */
    public function __construct(Settings $settings)
    {
        try {
            $this->env = $settings->environment;
            if ($this->env == 'homologacao') {
                $this->homologacao($settings->issuer->codMun);
            } else {
                $this->producao($settings->issuer->codMun);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * configuração para o ambiente de homologacao
     */
    private function homologacao($codMun): void
    {
        $this->wsdl = $this->urlHomolagation[$codMun];
        $this->folder = 'homologacao';
    }

    /**
     * configuração para o ambiente de produção
     */
    private function producao($codMun): void
    {
        $this->wsdl = $this->urlProduction[$codMun];
        $this->folder = 'producao';
    }
}
