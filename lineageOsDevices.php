<?php
declare(strict_types = 1);

class lineageOsDevices
{
    /**
     * @var string
     */
    private $sFilterForThisVersion;

    /**
     * @var bool
     */
    private $bShowProgress;

    /**
     * @var bool
     */
    private $bLogErrors;

    private $aRequestErrors = [];

    /**
     * @var string
     */
    private $sNoDevicesFound;

    private $aDeviceBrandNamesMissing = [];

    private $aDeviceVersionsMissing = [];


    /**
     * lineageOsDevices constructor.
     *
     * @param string $sFilterForThisVersion
     * @param bool $bShowProgress
     * @param bool $bLogErrors
     */
    public function __construct(string $sFilterForThisVersion = "", bool $bShowProgress = false, bool $bLogErrors = false)
    {
        $this->sFilterForThisVersion = $sFilterForThisVersion;
        $this->bShowProgress = $bShowProgress;
        $this->bLogErrors = $bLogErrors;
    }

    /**
     * @return array
     */
    public function getDevices() : array {
        $sResponse = $this->getCurlResponse('https://wiki.lineageos.org/sitemap.xml');
        $aMatches = $this->filterResponseAllDevices($sResponse);

        if (empty($aMatches)) {
            return $aMatches;
        }

        return $this->addAndStructureDeviceInformation($aMatches);
    }

    /**
     * @param array $aDevices
     *
     * @return array
     */
    private function addAndStructureDeviceInformation(array &$aDevices) : array {

        if ($this->bShowProgress) echo "Loading device information\n";

        foreach ($aDevices as $key => &$aDevice) {
            $aNamesAndVersions = $this->getNameAndVersions($aDevice['sUrl'], $aDevice['sCodeName']);

            /**
             * Information is missing, log and skip it
             */
            if (count($aNamesAndVersions) === 1) {
                if ($this->bLogErrors) {
                    if ($aNamesAndVersions[0] === true) {
                        $this->aDeviceVersionsMissing[] = $aDevice['sUrl'];
                    } else {
                        $this->aDeviceBrandNamesMissing[] = $aDevice['sUrl'];
                    }
                }

                unset($aDevices[$key]);
                continue;
            }

            if (
                !empty($this->sFilterForThisVersion)
                && !in_array($this->sFilterForThisVersion, $aNamesAndVersions['aVersions'])
            ) {
                unset($aDevices[$key]);
                continue;
            }

            $aDevice['sName'] = $aNamesAndVersions['sName'];
            $aDevice['sBrand'] = $aNamesAndVersions['sBrand'];
            $aDevice['aVersions'] = $aNamesAndVersions['aVersions'];

            if ($this->bShowProgress) echo ".";
        }

        if ($this->bShowProgress) echo "\nFinished";

        return array_values($aDevices);
    }

    /**
     * @param string $sUrl
     * @param string $sCodeName
     *
     * @return array
     */
    private function getNameAndVersions(string &$sUrl, string &$sCodeName) : array {
        return $this->filterResponseSingleDevice($this->getCurlResponse($sUrl), $sCodeName);
    }

    /**
     * @param string $sResponse
     *
     * @return array
     */
    private function &filterResponseAllDevices(string &$sResponse) : array {
        preg_match_all('/<loc>(https:\/\/wiki\.lineageos\.org\/devices\/([\da-z_\.\(\)\/\\\-]{1,40})\/)<\/loc>/i', $sResponse, $aMatches);
        $aDevices = [];

        if (empty($aMatches) || empty($aMatches[1]) || empty($aMatches[2])) {
            if ($this->bLogErrors) $this->sNoDevicesFound = $sResponse;
            return $aDevices;
        }


        foreach ($aMatches[1] as $i => &$sUrl) {
            $aDevices[] = [
                'sUrl' => $sUrl,
                'sCodeName' => $aMatches[2][$i],
            ];
        }

        unset($aMatches);

        if ($this->bShowProgress) echo count($aDevices) . " devices found\n";

        return $aDevices;
    }

    /**
     * @param string $sResponse
     * @param string $sCodeName
     *
     * @return array
     */
    private function filterResponseSingleDevice(string $sResponse, string $sCodeName) : array {
        preg_match_all('/<li>(13\.1|14\.1|15\.1)<\/li>/i', $sResponse, $aMatchesVersions);
        if (empty($aMatchesVersions) || empty($aMatchesVersions[1])) return [true]; // Device no longer supported

        preg_match('/class="header"><strong>([a-z]{1,20})\s/i', $sResponse, $aMatchesBrandName);
        if (empty($aMatchesBrandName) || empty($aMatchesBrandName[1])) return [false]; // Regex not working as intended

        preg_match(
            '/class="header"><strong>' . $this->prepareStringForRegex($aMatchesBrandName[1]) . '\s([a-z0-9\s.\/_&\-,+\)\(]{1,50})\s\(' . $this->prepareStringForRegex($sCodeName) . '/i',
            $sResponse,
            $aMatchesDeviceName);
        if (empty($aMatchesDeviceName) || empty($aMatchesDeviceName[1])) return [false]; // Regex not working as intended

        return [
            'sName' => $aMatchesDeviceName[1],
            'sBrand' => $aMatchesBrandName[1],
            'aVersions' => $aMatchesVersions[1]
        ];
    }

    /**
     * @param string $sUrl
     *
     * @return string
     */
    private function &getCurlResponse(string $sUrl) : string {
        $curl = curl_init($sUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $sResponse = curl_exec($curl);

        if (
            $this->bLogErrors
            && (curl_error($curl) !== ''  || curl_errno($curl) !== 0)
        ) {
            $this->aRequestErrors[] = [
                'url' => $sUrl,
                'response' => $sResponse,
                'error' => curl_error($curl),
                'errno' => curl_errno($curl),
            ];
        }

        curl_close($curl);
        unset($curl);

        return $sResponse;
    }

    public function getErrorInformation() : array {
        return [
            'aRequestErrors' => $this->aRequestErrors,
            'sNoDevicesFound' => $this->sNoDevicesFound,
            'aDeviceBrandNamesMissing' => $this->aDeviceBrandNamesMissing,
            'aDeviceVersionsMissing' => $this->aDeviceVersionsMissing,
        ];
    }

    private function prepareStringForRegex(string &$sString) : string {
        return str_replace(
            ['.', '(', ')', '/', '-', '\\'],
            ['\.', '\(', '\)', '\/', '\-', '\\\\'],
            $sString);
    }
}