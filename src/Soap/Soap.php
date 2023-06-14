<?php namespace NFse\Soap;

use Exception;
use NFse\Config\WebService;
use NFse\Models\Settings;
use SoapClient;
use SoapFault;

class Soap extends SoapClient
{
    private $xml;
    private $method;
    private $webservice;
    private $settings;

    /**
     * conecta ao webservice e verifica disponibilidade do serviço
     *
     * @param NFse\Models\Settings;
     * @param string;
     * @param array;
     */
    public function __construct(Settings $settings, string $method, array $options = null)
    {
        try {
            $this->webservice = new WebService($settings);
            $this->method = $method;
            $this->settings = $settings;

            //seta as opções de certificado digital e stream context
            if (is_null($options)) {
                $options = [
                    'encoding' => 'UTF-8',
                    'soap_version' => $this->webservice->soapVersion,
                    'style' => $this->webservice->style,
                    'use' => $this->webservice->use,
                    'trace' => $this->webservice->trace,
                    'compression' => $this->webservice->compression,
                    'exceptions' => $this->webservice->exceptions,
                    'connection_timeout' => $this->webservice->connectionTimeout,
                    'cache_wsdl' => $this->webservice->cacheWsdl,
                    'stream_context' => stream_context_create([
                        "ssl" => [
                            'local_cert' => $this->settings->certificate->folder . $this->settings->certificate->mixedKey,
                            "verify_peer" => $this->webservice->sslVerifyPeer,
                            "verify_peer_name" => $this->webservice->sslVerifyPeerName,
                        ],
                    ]),
                ];
            }

            try {
                parent::__construct($this->webservice->wsdl, $options);
            } catch (SoapFault $e) {
                throw new Exception("No momento o sistema da prefeitura está instável ou inoperante, tente novamente mais tarde.\nE - {$e->getMessage()}");
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * seta o xml do soap de entrada
     */
    public function setXML($xmlData): void
    {
        $this->xml = $xmlData;
    }

    /**
     * limpa o xml antes do envio
     */
    private function clearXml():void
    {
        $remove = ['xmlns:default="http://www.w3.org/2000/09/xmldsig#"', ' standalone="no"', 'default:', ':default', "\n", "\r", "\t", "  "];
        $encode = ['<?xml version="1.0"?>', '<?xml version="1.0" encoding="utf-8"?>', '<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0" encoding="utf-8" standalone="no"?>', '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'];
        $this->xml = str_replace(array_merge($remove, $encode), '', $this->xml);
    }

    //reescreve a chamada ao webservice
    public function __soapCall($function_name = null, $arguments = null, $options = null, $input_headers = null, &$output_headers = null)
    {
        return parent::__soapCall(str_replace('Request', '', $this->method), [
            'location' => $this->webservice->wsdl,
        ], $options, $input_headers, $output_headers); // TODO: Change the autogenerated stub
    }

    //monta o cabeçalho e chama o request ao webservice
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $this->clearXml();

        //monta a mensagem ao webservice
/*        $data = '<?xml version="1.0" encoding="utf-8"?>';*/
//        $data = '<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">';
        $data = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfse="http://nfse.abrasf.org.br">';
        $data .= '<soapenv:Header/>';
        $data .= '<soapenv:Body>';
        $data .= "<nfse:{$this->method}>";
        $data .= '<nfseCabecMsg>';
        $data .= '<cabecalho versao="1.00" xmlns="http://www.abrasf.org.br/nfse.xsd"><versaoDados>2.04</versaoDados></cabecalho>';
        $data .= '</nfseCabecMsg>';
        $data .= '<nfseDadosMsg>';
        $data .= $this->xml;
        $data .= '</nfseDadosMsg>';
        $data .= "</nfse:{$this->method}>";
        $data .= '</soapenv:Body>';
        $data .= '</soapenv:Envelope>';

        try {
            dd($data);
            $response = parent::__doRequest($data, $location, $action, $version, $one_way);
        } catch (\SoapFault $a) {
            dd('soap error', $a);
            throw new \Exception("Não foi possivel se conectar ao sistema da prefeitura, tente novamente mais tarde.<br>E - {$a->getMessage()}");
        } catch (\Exception $b) {
            dd('exception', $b);
            throw new \Exception("No momento o sistema da prefeitura está instável ou inoperante, tente novamente mais tarde.<br>E - {$b->getMessage()}");
        }
dd('response', $response);
        return $response;
    }
}
