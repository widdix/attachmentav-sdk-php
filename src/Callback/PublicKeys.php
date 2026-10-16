<?php

declare(strict_types=1);

namespace AttachmentAV\Callback;

use AttachmentAV\Region;

/**
 * Public keys (PEM X509) used to verify the signature of callbacks, per region of the attachmentAV API (SaaS).
 *
 * @see https://attachmentav.com/help/virus-malware-scan-api/developer/publickey.html
 */
final class PublicKeys
{
    private const KEYS = [
        Region::EU => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyLoZzjo1cQV9ZN2TH/al
rxWiQ3u/ndT0HMrLMdBTVO3Tz1nUjLt6SqKZsN8dQhvPoEjfyhCTEg7MPWopG3n0
cf3NRxtoeXy/Z62b1zdUd426kMuKOQP8Yy6cxa/RtK2tkHCnTGxjfvNmMK+m68sF
msilR88LnIN71my4cG8bIDGDftWublvKAEOWhxSECYn1XEtyrQL5lm8HFnHdE9ys
56xTJkdr5Mmkvanrnd/hXzTHzjruGcLvbjciI82+Z335AzYgJcnmH4/zsBuyPL2F
JSfQF9NsPaTJuQgkw1usAKBQcujcEriYUDNWgTe1a+LOnCEMb+9mAYw8lMRYRd3C
BwIDAQAB
-----END PUBLIC KEY-----
PEM,
        Region::US => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvleaPg1In3dWHoVG7mL6
7HIeClPzLNP6S5SZFv1XBZeRV1CNkcwYOxKGkuD559293RhsBZHPDyyCSDroHeC+
rKl+y01G/tTyNTq1chMtMv+8jflRh1FeqQLV9Ir6qZc7XdL+dqO5RoalvBti5Y+A
72QaUPO6s73V87kkKqR0OQAlm6H2AFvrlDpkZxqcvJ8e2OzUoTGEY9AIt4SZgmWu
bgXxz9C7dMyjDPqjWoEXE9qjBBftrhkoabUnoFB+MqpvExzxZhGAJJgEfnSK3FOY
pcvQIlg4MOC3M5CnWWXU7RurQYdCaL7YSDTRujKVnZlTCLk8B/ecRv2/SoouYsMU
NwIDAQAB
-----END PUBLIC KEY-----
PEM,
        Region::CANADA => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmwB9VfSrcsF1reMKpEbB
4HDdyx9H2959fxz+bDCaopNF6OFLUqg9q5KkZZYl1iCZwnay6FX5Ws0yeJhldWgn
MIh2rCPErVRrfM6k+oJIwQEiJONc8GxPK/SA3lEg0Xf7WLAH92+dYoCLBohoC8df
kOTTzTQFr+SlsHkWD9+QspFG2MDvi9LA4hvDSEnJ53MTp1gEw0JdMmIWuvf+77Qh
KssMchg2eeAsB5z7a0XXSQnQ0/BzvNg7nqXIgnlD3OgE7X8LBaiUH8aGyazrLDpG
Fg/JFiOtz2461C7+I6wNBWa0bWYS2ujHtt2s2Uxq4h0Wwh83AKLON3hi1MDuFZiS
3wIDAQAB
-----END PUBLIC KEY-----
PEM,
        Region::INDIA => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAs4G2kKYf7Wkla8DzOKZp
LygN/Pzl0shSjnZB16WwKcs89yrFo6jobRM4/HHaltab/66DK1Vl3I7pwi5HMlFj
7N6DFI8BjhVDsF1uMvc7CfQJ452QFF37S5Fjspb+IhLFTOzwxUHOyGrpwkfCcZhU
yaChhPOANCLVqo8XFgvXOEa1B5i11AUKcTPZ6SEJb3x7SssZ7nk2Tag9G4B2W7Id
bXeK/g+WPDnbhY9WW0iSm1khdPgDTXca3accuvtdKEd/FayxHnMQenVErwOT5pEm
tMtz0KinPAzlqKmyGRZdJ/OvjCaZNwuFkuEhdnwU/RdNukobC2xBDICgamEEX+8U
FwIDAQAB
-----END PUBLIC KEY-----
PEM,
        Region::AUSTRALIA => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArmkSxz3uHTpDrPtcou1C
a33BMfVBKYNZpE7aVb3lcR0GPujo5tIcjfmj/Ij3mzcCJqdFUjimS9LdY+xxx2zX
jTUPhNmOIpd7WNr8SzlOF3u1m6b175a7GVKtJjF9/2FBhsp823grS18Y8o917zOS
69yqpERHN57l0V1JwBW4BajoIcN3G4YhI6CSQVo2BClJevxuKMhMwtdWD9E5f/b+
sBkQ1ssBFvWXLzlPrzYpHk9gI9x+fr2+d5WQCnXTq4rZFrm0OkR1JQs578BZBhSu
1GbXSZbQ8mWJDn31PdQY+CJz+76wL/9V6E5RAp6HY4ED6uUf4cUmXNjT43/q9CnR
gwIDAQAB
-----END PUBLIC KEY-----
PEM,
    ];

    private function __construct()
    {
    }

    public static function forRegion(string $region): string
    {
        return self::KEYS[Region::normalize($region)];
    }
}
