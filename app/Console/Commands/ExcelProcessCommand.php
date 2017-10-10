<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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
        $directory_json = env('PATH_JSON_FILES');
        $fileNames = collect(Storage::disk('ftp_excel')->files());
        $fileNames->each(function ($filename) use($directory) {
            $contents = Storage::disk('ftp_excel')->get($filename);
            \File::put($directory.'/'.$filename, $contents);
        });
        $files = \File::allFiles(base_path($directory));
        foreach ($files as $file) {
            $json = collect();
            $dat = collect();
            $filepath = (string) $file;
            $objPHPExcel = \PHPExcel_IOFactory::load($filepath);
            $sheetObjs = $objPHPExcel->getAllSheets();
            foreach ($sheetObjs as $sheetObj) {
                $i = 1;
                foreach ($sheetObj->getRowIterator(1, null) as $data) {
                    if($i==1){
                        $header = $this->parseHeader($data);
                    } else {
                        $row = $this->parseRow($data, $header);
                        $dat->push(["clientid" => $row['CLIENTE_ID'],
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
            $json->push(['DATA' => $dat->toArray()]);
            \File::put($directory_json.'/'.explode('.',$file->getFilename())[0].'.json', $json->toJson());
        }
    }

    public function parseHeader($row)
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
}
