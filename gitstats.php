<?php
/*
 * Copyright (C) 2010 Robin Burchell <robin.burchell@collabora.co.uk>
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms and conditions of the GNU Lesser General Public License,
 * version 2.1, as published by the Free Software Foundation.
 *
 * This program is distributed in the hope it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin St - Fifth Floor, Boston, MA 02110-1301 USA.
 */

$aCommitData = array();
exec('git log --no-merges | grep -B1 "^Author:"', $aCommitData);

$iMaxLines = count($aCommitData);

// commit 33f9b5435edcf56555745e19896a14c790a33b1d
// Author: Eduardo M. Fleury <eduardo.fleury@openbossa.org>
// --
// commit 2bfa405a9798372624c410d3d13fa143985440b8
// Author: Caio Marcelo de Oliveira Filho <caio.oliveira@openbossa.org>
// --
// commit c74a5ae953899b9109ef56b2057b094152616480
// --
//

for ($i = 0; $i < $iMaxLines; ++$i) {
    if (!isset($aCommitData[$i]))
        die("done\n");

    $sLine = $aCommitData[$i];
    if ($sLine[0] == '-') {
        if (!isset($aCommitData[$i + 2])) {
            die("done\n");
        }

        // we care about commit, then author
        if ($aCommitData[$i + 2][0] == '-') {
            $i++;
            continue;
        }
    } else if ($sLine[0] == 'c') {
        // sha
        $sCommit = array_pop(explode(" ", $sLine));
    } else if ($sLine[0] == 'A') {
        // author
        $sAuthor = implode(" ", array_slice(explode(" ", $sLine), 1));
    }

    if (isset($sCommit, $sAuthor)) {
        ContributorRepo::addCommit($sCommit, $sAuthor);
        unset($sCommit);
        unset($sAuthor);
    }
}

abstract class Contributor
{
    private $iLinesAddedCount = 0;
    public function setLinesAddedCount($iLinesAdded)
    {
        $this->iLinesAddedCount = $iLinesAdded;
    }

    public function linesAddedCount()
    {
        return $this->iLinesAddedCount;
    }

    private $iLinesRemovedCount = 0;
    public function setLinesRemovedCount($iLinesRemoved)
    {
        $this->iLinesRemovedCount = $iLinesRemoved;
    }

    public function linesRemovedCount()
    {
        return $this->iLinesRemovedCount;
    }

    private $iCommitCount = 0;
    public function setCommitCount($iCommits)
    {
        $this->iCommitCount = $iCommits;
    }

    public function commitCount()
    {
        return $this->iCommitCount;
    }

    private $sName = "(none)";
    public function setName($sName)
    {
        $this->sName = $sName;
    }

    public function name()
    {
        return $this->sName;
    }
}

class Organisation extends Contributor
{
    private $aPeople = array();
    public function addPerson($oPerson)
    {
        // if they are already a member of this org, don't add them again.
        foreach ($this->people() as $oPersonInOrg) {
            if ($oPersonInOrg == $oPerson) {
                return;
                break;
            }
        }

        // add them as a contributor
        $this->aPeople[] = $oPerson;
    }

    public function people()
    {
        return $this->aPeople;
    }
}

class Person extends Contributor
{
    private $oOrganisation;
    public function setOrganisation($oOrganisation)
    {
        if ($this->oOrganisation) {
//            if ($this->oOrganisation != $oOrganisation)
//                echo $this->name() . " is leaving " . $this->oOrganisation->name() .
//                                     " to go to " . $oOrganisation->name() . "\n";
        } else {
//            echo $this->name() . " started at " . $oOrganisation->name() . "\n";
        }

        $this->oOrganisation = $oOrganisation;
        $oOrganisation->addPerson($this);
    }

    public function organisation()
    {
        return $this->oOrganisation;
    }

    private $sEmail = "(none)";
    public function setEmail($sEmail)
    {
        $this->sEmail = $sEmail;
    }

    public function email()
    {
        return $this->sEmail;
    }
}

abstract class ContributorRepo
{
    // collectives
    public static $aOrganisations = array();

    // individuals
    public static $aPersons = array();

    private static function findOrganisation($sOrganisation)
    {
        $sOrganisation = strtolower($sOrganisation);

        if (isset(self::$aOrganisations[$sOrganisation]))
            return self::$aOrganisations[$sOrganisation];

        return null;
    }

    private static function findPerson($sPerson)
    {
        $sPerson = strtolower($sPerson);

        if (isset(self::$aPersons[$sPerson]))
            return self::$aPersons[$sPerson];

        return null;
    }

    public static function addCommit($sCommit, $sAuthor)
    {
        // Get author info seperated first
        // Bradley T. Hughes <bradley.hughes@nokia.com>
        $aAuthorBits = array();
        if (!preg_match("/(.+) \<(.+)(?:@| at )([^. ]*)[ .]?.*\>/", $sAuthor, $aAuthorBits))
            die("couldn't get author info for author " . $sAuthor . "\n");

        // matches are:
        // string 0
        // real name 1
        // email (from bit) 2
        // email (domain bit, first word only to avoid TLD madness) 3
        //
        // first, try find the Organisation this Person belongs to
        $oOrganisation = self::findOrganisation($aAuthorBits[3]);

        if (!$oOrganisation) {
            // create one
            $oOrganisation = new Organisation;
            $oOrganisation->setName($aAuthorBits[3]);

            // add under both name and email thingy
            self::$aOrganisations[strtolower($aAuthorBits[3])] = $oOrganisation;
        }

        // let's try find a Person
        $oPerson = self::findPerson($aAuthorBits[1]);

        if (!$oPerson) {
            // try again
           $oPerson = self::findPerson($aAuthorBits[2]);
        }

        if (!$oPerson) {
            // Person record doesn't exist, let's create one
            $oPerson = new Person;
            $oPerson->setName($aAuthorBits[1]);
            $oPerson->setEmail($sAuthor);

            // add under both name and email thingy
            self::$aPersons[strtolower($aAuthorBits[1])] = $oPerson;
            self::$aPersons[strtolower($aAuthorBits[2])] = $oPerson;
        }

        // always set their organisation, as people may move from one org to
        // another and keep contributing.
        $oPerson->setOrganisation($oOrganisation);

        // now parse the actual diff for stats purposes
        self::parseDiffForPerson($sCommit, $oPerson);
    }

    private static function parseDiffForPerson($sCommit, $oPerson)
    {
        // simple bookkeeping first
        $oPerson->setCommitCount($oPerson->commitCount() + 1);
        $oPerson->organisation()->setCommitCount($oPerson->organisation()->commitCount() + 1);

        $aCommitData = array();
        exec('git diff ' . $sCommit. '^1..' . $sCommit, $aCommitData);

        $iLinesAdded = 0;
        $iLinesRemoved = 0;

        foreach ($aCommitData as $sLine) {
            // don't care! :)
            if (trim($sLine) == "")
                continue;

            if (strlen($sLine) > 1 && $sLine[0] == '-' && $sLine[1] == '-') {
                // probably a diff header. I don't want to overcomplicate this
                // for now at least, so let's just hope someone doesn't remove
                // unindented code.
                continue;
            }

            if ($sLine[0] == '-')
                $iLinesRemoved++;
            else if ($sLine[0] == '+')
                $iLinesAdded++;
        }

        // bookkeeping
        $oPerson->setLinesRemovedCount($oPerson->linesRemovedCount() + $iLinesRemoved);
        $oPerson->organisation()->setLinesRemovedCount($oPerson->organisation()->linesRemovedCount() + $iLinesRemoved);
        $oPerson->setLinesAddedCount($oPerson->linesAddedCount() + $iLinesAdded);
        $oPerson->organisation()->setLinesAddedCount($oPerson->organisation()->linesAddedCount() + $iLinesAdded);
        echo "Parsed commit " . $sCommit . " for " . $oPerson->email() . "\n";

        // update csvs
        self::writeData();
    }

    private static function writeData()
    {
        // write orgs
        $aOrgs = array();
        $aOrgs[] = array("Organisation Name", "Commits", "Lines Added", "Lines Removed", "People");
        foreach (ContributorRepo::$aOrganisations as $oOrganisation) {
            $aOrgs[] = array($oOrganisation->name(), $oOrganisation->commitCount(),
                    $oOrganisation->linesAddedCount(), $oOrganisation->linesRemovedCount(),
                    count($oOrganisation->people()));

            // also write up a summary of the people in this org
            $aOrgSummary = array();
            $aOrgSummary[] = array("Name", "Email", "Commits", "Lines Added", "Lines Removed");
            $aOrgSummary[] = array($oOrganisation->name(), "",
                    $oOrganisation->commitCount(), $oOrganisation->linesAddedCount(),
                    $oOrganisation->linesRemovedCount());
            $aOrgSummary[] = array(); // blank line
            foreach ($oOrganisation->people() as $oPerson) {
                $aOrgSummary[] = array($oPerson->name(), $oPerson->email(),
                        $oPerson->commitCount(), $oPerson->linesAddedCount(),
                        $oPerson->linesRemovedCount());
            }

            // write it
            $rThisOrg = fopen("org-summary-" . $oOrganisation->name() . ".csv", "w");
            foreach ($aOrgSummary as $aLine) {
                fputcsv($rThisOrg, $aLine);
            }
            fclose($rThisOrg);
        }

        $rOrgs = fopen("orgs-overview.csv", "w");
        foreach ($aOrgs as $aLine) {
            fputcsv($rOrgs, $aLine);
        }


        // write contributors
        $aPeople = array();
        $aDonePeople = array();
        $aPeople[] = array("Name", "Email", "Organisation", "Commits", "Lines Added", "Lines Removed");
        foreach (ContributorRepo::$aPersons as $oPerson) {
            if (isset($aDonePeople[$oPerson->name()]))
                continue;

            // this has duplicates. remember? :)
            $aDonePeople[$oPerson->name()] = true;

            $aPeople[] = array($oPerson->name(), $oPerson->email(),
                    $oPerson->organisation()->name(),
                    $oPerson->commitCount(),
                    $oPerson->linesAddedCount(),
                    $oPerson->linesRemovedCount());
        }

        $rPeople = fopen("people-overview.csv", "w");
        foreach ($aPeople as $aLine) {
            fputcsv($rPeople, $aLine);
        }

    }
}


