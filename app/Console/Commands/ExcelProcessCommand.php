<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
                        $dat->push($row);
                    }
                    $i++;
                }
            }
            $json->push(['DATA' => $dat->toArray()]);
            dd($json->toJson());
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
