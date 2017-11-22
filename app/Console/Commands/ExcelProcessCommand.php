<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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


    protected $url = 'http://abanca.limetropy.com/backend/import/survey/';
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
        $directory_process = env('PATH_PROCESS_FILES');
        $directory_final = env('PATH_JSON_FILES');
        $directory_ftp = env('PATH_FTP_FILES');
        $attach = [];
        $fils = [];
        $fils_process = [];
        $fileNames = collect(Storage::disk('ftp_excel')->files());
        $fileNames->each(function ($filename) use($directory) {
            $contents = Storage::disk('ftp_excel')->get($filename);
            \File::put($directory.'/'.$filename, $contents);
            Storage::disk('ftp_excel')->delete($filename);
        });

        $files = \File::allFiles(base_path($directory));
        foreach ($files as $file) {
            $filepath = (string)$file;
            $finalFile = '';
            if (!preg_match('/CL[0-9]{6}/', $file->getFilename())) {
                continue;
            }
            $files = $this->getDataBase();
            if (in_array($file->getFilename(), $files)) {
                continue;
            }
            if (preg_match('/xls/', $file->getFilename())) {
                $data = $this->processExcel($filepath);
            } else {
                $data = $this->processTxt($filepath);
            }
            $fileData = $data->transform(function ($item, $key) {
                $client = new Client();
                try {
                    $url2 = "http://abanca.limetropy.com/backend/import/survey?clientid=" . $item['clientid'] . "&pasoid=" . $item['pasoid'] . "&name=" . $item['name'] . "&surname=" . $item['surname'] . "&digits=" . $item['digits'] . "&surveycode=" . $item['surveycode'] . "&email=" . $item['email'] . "&clustercode=" . $item['clustercode'] . "&branchcode=" . $item['branchcode'] . "&abancacode=" . $item['abancacode'];
                    $res = $client->request('GET', $url2);
                    $status = 'Created';
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $status = 'Error';
                    echo $e->getMessage();
                }
                return array_merge($item, ['status' => $status]);
            });
            $headers = [];
            foreach ($fileData[0] as $key => $val) {
                $headers[] = $key;
            }
            $lineHeader = $this->newLine($headers);
            $newContent = $this->newLineAll($fileData);
            $lineHeaderError = $this->newLine($headers, ';', false, 'status');
            $newContentError = $this->newLineAllError($fileData);
            if ($newContentError != '') {
                \File::put(base_path($directory_final . '/' . $file->getFilename() . '.txt'), $lineHeaderError . $newContentError);
                $attach[] = base_path($directory_final . '/' . $file->getFilename() . '.txt');
                $fils[] = $file->getFilename() . '.txt';
            }
            $fils_process[] = $file->getFilename();
            preg_match('/CL[0-9]{6}/', $file->getFilename(), $rest_name);
            $fi = $directory_ftp . '/ClientesCargados_' . str_replace('CL', '', $rest_name[0]) . '.csv';
            \File::put(base_path($fi), $lineHeader . $newContent);
            \File::move(base_path($directory . '/' . $file->getFilename()), base_path($directory_process . '/' . $file->getFilename()));
        }
        $this->putDataBase($fils_process);
        $lineas2 = $this->sendToFtp();
        $this->sendMail($attach, $fils, $lineas2);
    }

    public function sendToFtp()
    {
        $directory_ftp = env('PATH_FTP_FILES');
        $files_ftp = \File::allFiles(base_path($directory_ftp));
        $lineas2 = [];
        $lineas3 = [];
        foreach ($files_ftp as $file)
        {
            $filecontent = file_get_contents((string) $file);
            $filepath = (string) $file;
            try{
                \Storage::disk('sftp_server_final')->put('IN/'.$file->getFilename(), $filecontent);
                unlink($filepath);
                $lineas2[] = $file->getFilename();
            } catch (\Exception $e){
                $lineas3[] = $file->getFilename();
            }
        }
        return ['lineas2' => $lineas2, 'lineas3' => $lineas3];
    }

    public function processTxt($file)
    {
        $dat = collect();
        $file = fopen($file,'r');
        $i = 1;
        $header = [];
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
        $header = [];
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

    public function sendMail($attach, $files, $files2)
    {
        Mail::send('emails.send', array_merge(['lineas' => $files], $files2),  function ($m) use ($attach) {
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

    public function newLineAllError($arr, $delimiter = ';', $comillas = false)
    {
        $result = '';
        foreach($arr as $array){
            $newLine = '';
            if($array['status']=='Error'){
                foreach ($array as $key => $val){
                    if($key=='status'){
                        continue;
                    }
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
        }
        return $result;
    }

    public function newLine($array, $delimiter = ';', $comillas = false, $field = false)
    {
        $newLine = '';
        foreach ($array as $key => $val){
            if($val==$field && $field){
                continue;
            }
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

    public function getDataBase()
    {
        $filepath = storage_path('database/excel-process.txt');
        if(file_exists($filepath)){
            $fil = fopen($filepath,'r');
            while ($linea = fgets($fil)) {
                return explode(',', $linea);
            }
        }
        return [];
    }

    public function putDataBase($files)
    {
        $filepath = storage_path('database/excel-process.txt');
        $oldData = $this->getDataBase();
        $files = array_merge($oldData, $files);
        $filecontent = '';
        foreach ($files as $f){
            $filecontent.= $f.',';
        }
        \File::put($filepath, substr($filecontent, 0, strlen($filecontent)-1));
    }

}
