<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
use Mail;

class ExcelProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process files excel and parse to json';


    protected $url = 'http://abanca.limetropy.com';
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $directory = env('PATH_EXCEL_FILES');
        $directory_final = env('PATH_JSON_FILES');
        $attach = [];
        $fils = [];
        /*$fileNames = collect(Storage::disk('ftp_excel')->files());
        $fileNames->each(function ($filename) use($directory) {
            $contents = Storage::disk('ftp_excel')->get($filename);
            \File::put($directory.'/'.$filename, $contents);
        });*/
        $files = \File::allFiles(base_path($directory));
        foreach ($files as $file) {
            $filepath = (string) $file;
            $finalFile = '';
            if(preg_match('/xls/', $file->getFilename())){
                $data = $this->processExcel($filepath);
            } else {
                $data = $this->processTxt($filepath);
            }
            $fileData = $data->transform(function ($item, $key){
                $client = new Client();
                try{
                    $res = $client->post($this->url, ['form_params' => $item]);
                    if($res->getStatusCode()=='200'){
                        $status = 'Created';
                    } else {
                        $status = 'No created';
                    }
                } catch(\GuzzleHttp\Exception\ClientException $e){
                    $status = 'Error';
                    echo $e->getMessage();
                }
                return array_merge($item, ['status' =>$status]);
            });
            $headers = [];
            foreach($fileData[0] as $key => $val){
                $headers[] = $key;
            }
            $lineHeader = $this->newLine($headers);
            $newContent = $this->newLineAll($fileData);
            \File::put(base_path($directory_final.'/'.$file->getFilename().'.txt'), $lineHeader.$newContent);
            $attach[] = base_path($directory_final.'/'.$file->getFilename().'.txt');
            $fils[] = $file->getFilename().'.txt';
        }
        $this->sendMail($attach, $fils);
    }

    public function processTxt($file)
    {
        $dat = collect();
        $file = fopen($file,'r');
        $i = 1;
        while ($linea = fgets($file)) {
            if($i==1){
                $header = $this->parseHeaderTxt($linea);
            } else {
                $row = $this->getData($header, $linea, ';');
                $dat->push(["clientid" => $row['CLIENTE_ID'],
                    "pasoid" => $row['PASO_ID'],
                    "name" => $row['NOMBRE'],
                    "surname" => $row['APELLIDO'],
                    "digits" => $row['SECUENCIAL'],
                    "surveycode" => $row['CODIGO_MOMENTO'],
                    "email" => $row['CORREO'],
                    "clustercode" => $row['CLUSTER'],
                    "branchcode" => $row['COD_OFICINA'],
                    "abancacode" => $row['CODIGO_ABANCA']]);
            }
            $i++;
        }
        return $dat;
    }

    public function processExcel($file)
    {
        $dat = collect();
        $objPHPExcel = \PHPExcel_IOFactory::load($file);
        $sheetObjs = $objPHPExcel->getAllSheets();
        foreach ($sheetObjs as $sheetObj) {
            $i = 1;
            foreach ($sheetObj->getRowIterator(1, null) as $data) {
                if($i==1){
                    $header = $this->parseHeaderExcel($data);
                } else {
                    $row = $this->parseRow($data, $header);
                    $dat->push(["clientid" => $row['CLIENTE_ID'],
                        "pasoid" => $row['PASO_ID'],
                        "name" => $row['NOMBRE'],
                        "surname" => $row['APELLIDO'],
                        "digits" => $row['SECUENCIAL'],
                        "surveycode" => $row['CODIGO_MOMENTO'],
                        "email" => $row['CORREO'],
                        "clustercode" => $row['CLUSTER'],
                        "branchcode" => $row['COD_OFICINA'],
                        "abancacode" => $row['CODIGO_ABANCA']]);
                }
                $i++;
            }
        }
        return $dat;
    }

    public function parseHeaderTxt($row)
    {
        $data = preg_replace('/["\r\n]/','',$row);
        if(substr($data,-1)==';'){
            $data = substr($data, 0, strlen($data)-1);
        }
        return explode(';', $data);
    }

    public function parseHeaderExcel($row)
    {
        $data = [];
        foreach ($row->getCellIterator() as $cell) {
            $value = $cell->getCalculatedValue();
            $data[] = $value;
        }
        return $data;
    }

    private function parseRow($row, $arraylist)
    {
        $data = [];
        $i = 0;
        foreach ($row->getCellIterator() as $cell) {
            $value = $cell->getCalculatedValue();
            if(isset($arraylist[$i])){
                $data[$arraylist[$i]] = $value;
                $i++;
            }
        }
        return $data;
    }

    public function getData($headers, $data, $delimiter = ',', $comillas = false)
    {
        $response = [];
        $i = 0;
        $data = preg_replace('/\s\s+/', ' ', $data);
        if($comillas){
            $dat2 = explode('"'.$delimiter.'"', preg_replace('/[\r\n]/','',$data));
            foreach ($dat2 as $r){
                $dat[] = str_replace('"','',$r);
            }
        } else {
            $data = preg_replace('/["\r\n]/','',$data);
            if(substr($data,-1)==';'){
                $data = substr($data, 0, strlen($data)-1);
            }
            $dat = explode($delimiter, $data);
        }
        foreach ($headers as $key => $val){
            $response[$val] = $dat[$i];
            $i++;
        }
        return $response;
    }

    public function sendMail($attach, $files)
    {
        Mail::send('emails.send', ['lineas' => $files],  function ($m) use ($attach) {
            $m->from('processfiles@itwarp.com', 'Process files');
            foreach($attach as $at){
                $m->attach($at);
            }
            $m->to('Ariel.garbini@gmail.com', 'Ariel')->subject('Archivos procesados');
        });
    }

    public function newLineAll($arr, $delimiter = ';', $comillas = false)
    {
        $result = '';
        foreach($arr as $array){
            $newLine = '';
            foreach ($array as $key => $val){
                if($comillas){
                    $newLine.='"';
                }
                $newLine.= $val;
                if($comillas){
                    $newLine.='"';
                }
                $newLine.= ';';
            }
            $result.= substr($newLine,0,strlen($newLine)-1)."\r\n";
        }
        return $result;
    }

    public function newLine($array, $delimiter = ';', $comillas = false)
    {
        $newLine = '';
        foreach ($array as $key => $val){
            if($comillas){
                $newLine.='"';
            }
            $newLine.= $val;
            if($comillas){
                $newLine.='"';
            }
            $newLine.= ';';
        }
        return substr($newLine,0,strlen($newLine)-1)."\r\n";
    }
}
