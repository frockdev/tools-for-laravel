<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CurrentNumberRenderer;
use Illuminate\Console\Command;
use Nette\PhpGenerator\ClassType;

class GenerateGrafanaMetrics extends Command
{
    protected $signature = 'frock:generate-grafana-metrics {appName?}';
    public function handle() {
        $appName = $this->argument('appName') ?? config('app.name');
        $boards = [];
        $metrics = [];
        $files = $this->getDirContents(app_path());
        foreach ($files as $filePath) {
            if (!is_file($filePath)) continue;
            foreach ($this->getMetricsDataFromClass($filePath, $appName) as $metricName=>$metricInfo) {
                $metrics[$metricName] = $metricInfo;
                $this->info('Metric '.$metricName.' from '.$metricInfo['from'].' has been found');
                $renderer = app()->make($metricInfo['renderer'], ['metric'=>call_user_func($metricInfo['fullClassName'].'::getInstanceForRender')]);
                $grafanaMetricArray = $renderer->renderMetric();
                $grafanaMetricTerraformAlert = $renderer->renderAlerts();
                if (!array_key_exists($metricInfo['boardName'], $boards)) $boards[$metricInfo['boardName']] = ['rows'=>[], 'name'=>$metricInfo['boardName']];
                if (!array_key_exists($metricInfo['rowName'], $boards[$metricInfo['boardName']]['rows'])) $boards[$metricInfo['boardName']]['rows'][$metricInfo['rowName']] = ['panels'=>[]];
                $boards[$metricInfo['boardName']]['rows'][$metricInfo['rowName']]['panels'][$metricName] = [
                    'metric'=>$grafanaMetricArray,
                    'alert'=>$grafanaMetricTerraformAlert,
                ];
            }
        }
        if (empty($boards)) {
            $this->info('No boards generated.');
            return;
        }
        $this->generateBoardsAndAlerts($boards);
    }

    private function generateBoardsAndAlerts($boards) {
        $boardsDir = base_path().'/../grafana/boards';
        $terraformDir = base_path().'/../grafana/terraform';
        @mkdir($terraformDir, 0777, true);
        @mkdir($boardsDir, 0777, true);
        shell_exec('rm -rf '.$boardsDir.'/*');
        shell_exec('rm -rf '.$terraformDir.'/*.tf');
        $this->generateFoldersIerarchy($boards);
        foreach ($boards as $boardName=>$board) {
            $cleanedBoardName = $this->cleanUpBoardName($boardName);
            $boardArray = $this->getBoardTemplate();
            $boardArray['title'] = $this->getOnlyBoardNameFromOriginalBoardName($boardName);
            $boardArray['uid'] = $cleanedBoardName;

            foreach ($board['rows'] as $rowName=>$row) {
                $rowArray = $this->getRowTemplate();
                $rowArray['title'] = $rowName;
                $boardArray['panels'][] = $rowArray;

                foreach ($row['panels'] as $panel) {
                    if ($panel['alert']!=='') {
                        //todo
                    }
                    $boardArray['panels'][] = $panel['metric'];
                }
            }

            $folders = $this->getFoldersFromBoard($boardName);
            $folderTerraformName = strtolower(implode('_', $folders)).'_folder';

            file_put_contents(
                $boardsDir.'/'.$folderTerraformName.'_'.$cleanedBoardName.'.json',
                json_encode($boardArray, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
            );
            $this->createTerraformForBoard($cleanedBoardName, $boardName);
        }
    }

    private function getFoldersFromBoard(string $boardName) {
        $folders = [];
        $exploded = explode('/', $boardName);
        if (count($exploded)>1) {
            $folders = array_slice($exploded, 0, count($exploded)-1);
        } else {
            $folders[] = 'General';
        }
        return $folders;
    }

    private function getOnlyBoardNameFromOriginalBoardName(string $originalBoardName): string {
        $exploded = explode('/', $originalBoardName);
        return $exploded[count($exploded)-1];
    }

    private function createTerraformForBoard(string $cleanedBoardName, string $originalBoardName) {
        $folders = $this->getFoldersFromBoard($originalBoardName);
        $folderTerraformName = strtolower(implode('_', $folders)).'_folder';
        $terraformDir = base_path().'/../grafana/terraform';
        $pathToJsonBoard = './../boards/'.$folderTerraformName.'_'.$cleanedBoardName.'.json';
        $pathToTerraformFile = $terraformDir.'/'.$cleanedBoardName.'_board.tf';
        $terraformFileContents = '';
        $terraformFileContents .= '
            resource "grafana_dashboard" "'.$cleanedBoardName.'" {
                config_json = file("'.$pathToJsonBoard.'")
                folder = grafana_folder.'.$folderTerraformName.'.id
                depends_on = [grafana_folder.'.$folderTerraformName.']
            }
        ';
        file_put_contents($pathToTerraformFile, $terraformFileContents);
    }

    private function getRowTemplate() {
        return [
            'collapse'=>false,
            'collapsed'=>false,
            'gridPos'=>[
                'w'=>24,
            ],
            'panels'=>[],
            'showTitle'=>true,
            'targets'=>[
                [
                    'datasource'=>[
                        'type'=>'prometheus'
                    ],
                    'refId'=>'A',
                ],
            ],
            'title'=>'Current Bandwidth',
            'titleSize'=>'h6',
            'type'=>'row',
        ];
    }

    private function cleanUpBoardName($boardName) {
        $exploded = explode('/', $boardName);
        $boardName = $exploded[count($exploded)-1];
        $cleanedBoardName = str_replace(' ', '_', $boardName);
        $cleanedBoardName = str_replace('/', '_', $cleanedBoardName);
        $cleanedBoardName = str_replace('\\', '_', $cleanedBoardName);
        $cleanedBoardName = str_replace(':', '_', $cleanedBoardName);
        $cleanedBoardName = str_replace('?', '_', $cleanedBoardName);
        $cleanedBoardName = str_replace('&', '_', $cleanedBoardName);
        $cleanedBoardName = strtolower($cleanedBoardName);
        $cleanedBoardName = substr($cleanedBoardName, 0, 40);
        return $cleanedBoardName;
    }

    private function getBoardTemplate() {
        //"templating": {
        //    "list": [
        //      {
        //        "current": {
        //          "selected": false,
        //          "text": "PrometheusLocal",
        //          "value": "f2b2dddc-9198-4aa2-b9bb-4c158d2bf0ab"
        //        },
        //        "hide": 0,
        //        "includeAll": false,
        //        "multi": false,
        //        "name": "datasource",
        //        "options": [],
        //        "query": "prometheus",
        //        "refresh": 1,
        //        "regex": "",
        //        "skipUrlSync": false,
        //        "type": "datasource"
        //      }
        //    ]
        //  },
        return [
            'title'=>'',
            'timezone'=>'browser',
            'refresh'=>'30s',
            'templating'=>[
                'list'=>[
                    [
                        'hide'=>0,
                        'includeAll'=>false,
                        'multi'=>false,
                        'name'=>'datasource',
                        'options'=>[],
                        'query'=>'prometheus',
                        'refresh'=>1,
                        'regex'=>'',
                        'skipUrlSync'=>false,
                        'type'=>'datasource',
                    ],
                ],
            ],
        ];
    }

    private function getMetricsDataFromClass(string $filePath, string $appName) {
        $result = [];
        $fileContents = file_get_contents($filePath);
        $regex = "/([A-Z][A-Za-z0-9_]*)Metric::declare\(\)\s*;/m";
        $matches = [];
        preg_match_all($regex, $fileContents, $matches);
        if (empty($matches[1])) {
            return [];
        }
        $classNames = $matches[1];
        $fromClassName = $this->getFullCurrentClassName($fileContents);
        foreach ($classNames as $key=>$className) {
            $className = $className.'Metric';
            $fullMetricClassName = $this->getFullMetricClassName($fileContents, $className);
            $classType = $this->createClassTypeByClassName($fullMetricClassName);
            $metricName = $this->getMetricNameFromClassType($classType);
            $description = $this->getMetricDescriptionFromClassType($classType);
            $labels = $this->getMetricLabelsFromClassType($classType);
            $rowName = $this->getRowNameFromClassType($classType);
            $boardName = $this->getBoardNameFromClassType($classType, $appName);
            $buckets = $this->getBucketsFromClassType($classType);
            $renderer = $this->getRendererFromClassType($classType);
            $result[$metricName.'_'.$className] = [
                'className'=>$className,
                'fullClassName'=>$fullMetricClassName,
                'metricName'=>$metricName,
                'description'=>$description,
                'from'=>$fromClassName,
                'labels'=>$labels,
                'rowName'=>$rowName,
                'boardName'=>$boardName,
                'buckets'=>$buckets,
                'renderer'=>$renderer,
            ];
        }

        return $result;
    }

    private function getFullMetricClassName(string $fileContents, string $shortClassName) {
        //lets get "use" of class $metricInfo['className'] with regex
        $regex = "/use\s+([A-Za-z0-9_\\\\]*)\s*;/m";
        $matches = [];
        preg_match_all($regex, $fileContents, $matches);
        $classNames = $matches[1];
        foreach ($classNames as $className) {
            if (str_ends_with($className, $shortClassName)) {
                return $className;
            }
        }

        //lets find full classname of $metricInfo['className'] in file with regex
        $regex = '/([a-zA-Z\\\\]*'.$shortClassName.')::declare/m';
        $matches = [];
        preg_match($regex, $fileContents, $matches);
        return $matches[1];
    }

    private function getDirContents($dir, &$results = array()) {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
            } else if ($value != "." && $value != "..") {
                $this->getDirContents($path, $results);
                $results[] = $path;
            }
        }

        return $results;
    }

    private function getFullCurrentClassName(string $fileContents)
    {
        $regex = '/namespace\s+([A-Za-z0-9_\\\\]*)\s*;/m';
        $matches = [];
        preg_match($regex, $fileContents, $matches);
        $namespace = $matches[1];
        $regex = '/class\s+([A-Za-z0-9_]*)\s*/m';
        $matches = [];
        preg_match($regex, $fileContents, $matches);
        $className = $matches[1];
        return $namespace.'\\'.$className;
    }

    private function getMetricNameFromClassType(ClassType $classType)
    {
        if (!$classType->hasConstant('METRIC_NAME') || $classType->getConstant('METRIC_NAME')->getValue()==='') {
            throw new \Exception('Check Metrics. There is no METRIC_NAME constant in '.$classType->getName());
        }
        return $classType->getConstant('METRIC_NAME')->getValue();
    }

    private function getMetricDescriptionFromClassType(ClassType $classType)
    {
        if (!$classType->hasConstant('DESCRIPTION') || $classType->getConstant('DESCRIPTION')->getValue()==='') {
            throw new \Exception('Check Metrics. There is no DESCRIPTION constant in '.$classType->getName());
        }
        return $classType->getConstant('DESCRIPTION')->getValue();
    }

    private function createClassTypeByClassName(string $className): ClassType
    {
//        $filePath = (str_replace('\\', '/', $className));
//        $filePath = str_replace('App/', app_path().'/', $filePath).'.php';
        $reflectionClass = new \ReflectionClass($className);
        $filePath = $reflectionClass->getFileName();
        /** @var ClassType $classType */
        $classType = ClassType::fromCode(file_get_contents($filePath));
        return $classType;
    }

    private function getMetricLabelsFromClassType(ClassType $classType)
    {
        if (!$classType->hasConstant('LABELS')) {
            return [];
        }
        $labels = $classType->getConstant('LABELS')->getValue();
        if (!is_array($labels)) {
            throw new \Exception('Check Metrics. LABELS constant in '.$classType->getName().' is not array');
        }
        return $labels;
    }

    private function getRowNameFromClassType(ClassType $classType)
    {
        if (!$classType->hasConstant('ROW_NAME') || $classType->getConstant('ROW_NAME')->getValue()==='') {
            return 'undefined row';
        }
        return $classType->getConstant('ROW_NAME')->getValue();
    }

    private function getBoardNameFromClassType(ClassType $classType, string $appName)
    {
        if (!$classType->hasConstant('BOARD_NAME') || $classType->getConstant('BOARD_NAME')->getValue()==='') {
            throw new \Exception('Check Metrics. There is no BOARD_NAME constant in '.$classType->getName());
        }
        $appName = $this->fixNameFromAnyToCamelCase($appName);
        return 'GeneratedBoards/'.$appName.'/'.$classType->getConstant('BOARD_NAME')->getValue();
    }

    private function fixNameFromAnyToCamelCase(string $string): string
    {
        $string = preg_replace("/[^\w0-9]+/", '-', $string);
        $exploded = explode('-', $string);
        foreach ($exploded as &$word) {
            $word = ucfirst($word);
        }
        return implode('', $exploded);
    }

    private function getBucketsFromClassType(ClassType $classType)
    {
        if (!$classType->hasConstant('BUCKETS')) {
            return [];
        }
        $buckets = $classType->getConstant('BUCKETS')->getValue();
        if (!is_array($buckets)) {
            throw new \Exception('Check Metrics. BUCKETS constant in '.$classType->getName().' is not array');
        }
        return $buckets;
    }

    private function getRendererFromClassType(ClassType $classType)
    {
        if (!$classType->hasConstant('RENDERER') || $classType->getConstant('RENDERER')->getValue()==='') {
            return CurrentNumberRenderer::class;
        }
        return $classType->getConstant('RENDERER')->getValue();
    }

    private function generateFoldersIerarchy($boards)
    {
        foreach ($boards as $boardName=>$board) {
            $folders = $this->getFoldersFromBoard($boardName);
            $terraformPath = base_path().'/../grafana/terraform';

            $firstFolder = $folders[0];
            $terraformResource = '
                resource "grafana_folder" "'.strtolower($firstFolder).'_folder" {
                    title = "'.$firstFolder.'"
                }';
            file_put_contents($terraformPath.'/'.strtolower($firstFolder).'_folder.tf', $terraformResource);
            $parentFolder = strtolower($firstFolder);
            for ($i=1; $i<count($folders); $i++) {
                $terraformResource = '
                    resource "grafana_folder" "'.$parentFolder.'_'.strtolower($folders[$i]).'_folder" {
                        title = "'.$folders[$i].'"
                        parent_folder_uid = grafana_folder.'.strtolower($folders[$i-1]).'_folder.uid
                    }';
                file_put_contents($terraformPath.'/'.$parentFolder.'_'.strtolower($folders[$i]).'_folder.tf', $terraformResource);
                $parentFolder = $parentFolder.'_'.strtolower($folders[$i]);
            }
        }
    }
}
