<?php

namespace Acme\Network;

enum IPv6Type
{
    case Unspecified;
    case Loopback;      // ::1
    case IPv4Mapped;    // ::ffff/96
    case UniqueLocal;   // f::/7
    case LinkLocal;     // fe80::/10
    case Multicast;     // ff00::/8
    case GlobalUnicast; // 2000::/3

    public static function getIPv6Type(IPv6 $ipv6): IPv6Type | bool
    {
        $segmentedIPv6 = explode(':', $ipv6->getAddress());

        if (count($segmentedIPv6) === 0) {
            return false;
        } else if ($segmentedIPv6[0] === 'ff00') {
            return self::Multicast;
        } else if ($segmentedIPv6[0] === 'fe80') {
            return self::LinkLocal;
        } else if ($segmentedIPv6[0] === 'ffff') {
            return self::IPv4Mapped;
        } else if (!array_search($segmentedIPv6[0], ['1', '2', '3', '4', '5']) && count($segmentedIPv6) === 3) {
            return self::Loopback;
        } else if (str_split($segmentedIPv6[0])[0] === 'f') {
            return self::UniqueLocal;
        }

        return self::GlobalUnicast;
    }
}
