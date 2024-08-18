<?php

declare(strict_types=1);

namespace WyriHaximus\Github\Actions\NextSemVers;

use Version\Exception\InvalidVersionString;
use Version\Version;

use function count;
use function explode;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

use const WyriHaximus\Constants\Numeric\ONE;
use const WyriHaximus\Constants\Numeric\TWO;

final class Next
{
    private const PRE_RELEASE_CHUNK_COUNT = 2;
    private const PREFIXES                = ['v', 'release-'];

    public static function run(string $versionString, string $minimumVersionString, bool $strict): string
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($versionString, $prefix)) {
                $versionString = substr($versionString, strlen($prefix));
            }

            // this code structure is weird but required by the linter
            if (! str_starts_with($minimumVersionString, $prefix)) {
                continue;
            }

            $minimumVersionString = substr($minimumVersionString, strlen($prefix));
        }

        try {
            $version = Version::fromString($versionString);
        } catch (InvalidVersionString $invalidVersionException) {
            if ($strict === true) {
                throw $invalidVersionException;
            }

            if (count(explode('.', $versionString)) === ONE + TWO) {
                throw $invalidVersionException;
            }

            // split versionString by '-' (in case it is a pre-release)
            if (strpos($versionString, '-') !== false) {
                // because of psalm linting we can't do this
                // [$versionString, $preRelease] = explode('-', $versionString, self::PRE_RELEASE_CHUNK_COUNT);

                $versionStringAndPreRelease = explode('-', $versionString, self::PRE_RELEASE_CHUNK_COUNT);
                $versionString              = $versionStringAndPreRelease[0] ?? $versionString;
                $preRelease                 = $versionStringAndPreRelease[1] ?? 'none';

                $versionString               .= '.0-' . $preRelease;
            } else {
                $versionString .= '.0';
            }

            return self::run($versionString, $minimumVersionString, $strict);
        }

        $originalVersion = $version;

        $wasPreRelease = false;

        // if current version is a pre-release
        if ($version->isPreRelease()) {
            // get current version by removing anything else (e.g., pre-release, build-id, ...)
            $version       = Version::from($version->getMajor(), $version->getMinor(), $version->getPatch());
            $wasPreRelease = true;
        }

        $minVersion = Version::fromString($minimumVersionString);
        if ($version->isLessThan($minVersion)) {
            $version         = $minVersion;
            $originalVersion = $minVersion;
        }

        ///////////////////////////////////////////////////////////////////////////////////////////////
        // Raw versions
        ///////////////////////////////////////////////////////////////////////////////////////////////
        $output  = 'current=' . $originalVersion . "\n";
        $output .= 'major=' . $version->incrementMajor() . "\n";
        $output .= 'minor=' . $version->incrementMinor() . "\n";

        ///////////////////////////////////////////////////////////////////////////////////////////////
        // v prefixed versions
        ///////////////////////////////////////////////////////////////////////////////////////////////
        $output .= 'v_current=v' . $originalVersion . "\n";
        $output .= 'v_major=v' . $version->incrementMajor() . "\n";
        $output .= 'v_minor=v' . $version->incrementMinor() . "\n";

        // check if current version is a pre-release
        if ($wasPreRelease) {
            // use current version (without pre-release)
            $output .= 'patch=' . $version . "\n";
            // v prefixed versions
            $output .= 'v_patch=v' . $version . "\n";
        } else {
            // increment major/minor/patch version
            $output .= 'patch=' . $version->incrementPatch() . "\n";
            // v prefixed versions
            $output .= 'v_patch=v' . $version->incrementPatch() . "\n";
        }

        return $output;
    }
}
