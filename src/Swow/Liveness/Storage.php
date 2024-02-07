<?php

namespace FrockDev\ToolsForLaravel\Swow\Liveness;

class Storage
{
    private $states = [];

    private $times = [];

    private array $lastTimes = [];

    private array $lastTries = [];

    private array $modes = [];

    public function setLiveness(string $componentName, int $componentState, string $componentMessage, string $mode)
    {
        if ($mode===Liveness::MODE_1_SEC) {
            if (isset($this->lastTimes[$componentName]) && time()-$this->lastTimes[$componentName]===0) {
                return;
            }
        } elseif ($mode===Liveness::MODE_5_SEC) {
            if (isset($this->lastTimes[$componentName]) && time()-$this->lastTimes[$componentName]<=5) {
                return;
            }
        } elseif ($mode===Liveness::MODE_ONCE) {
            if (array_key_exists($componentName, $this->lastTries)) {
                return;
            }
        } elseif ($mode===Liveness::MODE_EACH_5_TRY) {
            if (isset($this->lastTries[$componentName]) && $this->lastTries[$componentName]<=5) {
                $this->lastTries[$componentName]++;
                return;
            }
        } elseif ($mode===Liveness::MODE_EACH_10_TRY) {
            if (isset($this->lastTries[$componentName]) && $this->lastTries[$componentName]<=10) {
                $this->lastTries[$componentName]++;
                return;
            }
        }
        $dataObject = new DataObject($componentState, $componentMessage);
        $this->states[$componentName] = $dataObject;
        $this->times[$componentName] = time();
        $this->lastTries[$componentName] = 0;
        $this->lastTimes[$componentName] = time();
        $this->modes[$componentName] = $mode;
    }

    public function getReportData(): array
    {
        $report = [];
        foreach ($this->states as $componentName => $dataObject) {
            $report[$componentName] = [
                'state' => $dataObject->componentState,
                'message' => $dataObject->componentMessage,
                'time' => $this->times[$componentName],
                'mode'=>$this->modes[$componentName]
            ];
        }
        return $report;
    }

    public function renderReportAsAText() {
        $report = $this->getReportData();
        $text = '';
        foreach ($report as $componentName => $data) {
            $text .= sprintf(
                "%s: %s\t%s\t%s\t%s\t\n",
                $componentName,
                $data['state'],
                $data['message'],
                date('Y-m-d H:i:s', $data['time']),
                $data['mode']
            );
        }
        return $text;
    }

    public function renderReportAsAHtmlTable() {
        $report = $this->getReportData();
        $html = '<table border="1">';
        $html .= '<tr><th>Component</th><th>State</th><th>Message</th><th>Time</th><th>Mode</th></tr>';
        foreach ($report as $componentName => $data) {
            $html .= sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                $componentName,
                $data['state'],
                $data['message'],
                date('Y-m-d H:i:s', $data['time']),
                $data['mode']
            );
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * @return int
     * in DataObject there is field componentState
     * it is http code.
     * this function should go over all the states and find biggest code.
     * if there is no states, it should return 200
     */
    public function calculateCommonCode(): int {
        $maxCode = 200;
        foreach ($this->states as $componentName => $dataObject) {
            if ($dataObject->componentState>$maxCode) {
                $maxCode = $dataObject->componentState;
            }
        }
        return $maxCode;
    }
}
