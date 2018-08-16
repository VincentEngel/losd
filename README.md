# losd

Get an array of all devices currently supported by LineageOs.

To get all devices running the 15.1 version:
```
(new lineageOsDevices('15.1'))->getDevices();
```

Output looks like this:
```
[
    {
        "sUrl": "https://wiki.lineageos.org/devices/FP2/",
        "sCodeName": "FP2",
        "sName": "FP2",
        "sBrand": "Fairphone",
        "aVersions": [
            "14.1",
            "15.1"
        ]
    },
    {
        "sUrl": "https://wiki.lineageos.org/devices/kltekor/",
        "sCodeName": "kltekor",
        "sName": "Galaxy S5 LTE (G900K/L/S)",
        "sBrand": "Samsung",
        "aVersions": [
            "14.1",
            "15.1"
        ]
    }
]
```
