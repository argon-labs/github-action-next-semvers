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

                $versionString .= '.0-' . $preRelease;
            } else {
                $versionString .= '.0';
            }

            return self::run($versionString, $minimumVersionString, $strict);
        }

        $wasPreRelease = false;

        // if current version is a pre-release
        if ($version->isPreRelease()) {
            // get current version by removing anything else (e.g., pre-release, build-id, ...)
            $version       = Version::from($version->getMajor(), $version->getMinor(), $version->getPatch());
            $wasPreRelease = true;
        }

        $majorVersion = $version->incrementMajor();
        $minorVersion = $version->incrementMinor();

        // use current version (without pre-release)
        $patchVersion = $version;

        // check if current version is a pre-release or not
        if (! $wasPreRelease) {
            // increment major/minor/patch version
            $patchVersion = $version->incrementPatch();
        }

        // set each version to min version if less than it
        $minVersion = Version::fromString($minimumVersionString);

        if ($majorVersion->isLessThan($minVersion)) {
            $majorVersion = $minVersion;
        }

        if ($minorVersion->isLessThan($minVersion)) {
            $minorVersion = $minVersion;
        }

        if ($patchVersion->isLessThan($minVersion)) {
            $patchVersion = $minVersion;
        }

        ///////////////////////////////////////////////////////////////////////////////////////////////
        // Raw versions
        ///////////////////////////////////////////////////////////////////////////////////////////////
        $output .= 'major=' . $majorVersion . "\n";
        $output .= 'minor=' . $minorVersion . "\n";
        $output .= 'patch=' . $patchVersion . "\n";

        ///////////////////////////////////////////////////////////////////////////////////////////////
        // v prefixed versions
        ///////////////////////////////////////////////////////////////////////////////////////////////
        $output .= 'v_major=v' . $majorVersion . "\n";
        $output .= 'v_minor=v' . $minorVersion . "\n";
        $output .= 'v_patch=v' . $patchVersion . "\n";

        return $output;
    }
}
