$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

# Read-only controls; this script contains no backup, restore, or ACL mutation logic.
$ReadOnlyValidationMode = $true
$CreateBackups = $false
$ApplyAclChanges = $false

$Targets = @(
    [pscustomobject]@{
        Name = 'conf'
        WorkingDirectory = 'C:\wamp\bin\apache\apache2.4.54.2'
        RelativeRoot = 'conf'
        FullRoot = 'C:\wamp\bin\apache\apache2.4.54.2\conf'
    },
    [pscustomobject]@{
        Name = 'alias'
        WorkingDirectory = 'C:\wamp'
        RelativeRoot = 'alias'
        FullRoot = 'C:\wamp\alias'
    }
)

# Intentionally unset. Values here are not proof of approval or validation.
$ApprovedBaselineSha256 = @{ conf = ''; alias = '' }
$ApprovedRemoveAceManifest = @{ conf = @(); alias = @() }
$BackupPaths = @{ conf = ''; alias = '' }

$SystemSid = 'S-1-5-18'
$AdministratorsSid = 'S-1-5-32-544'
$UsersSid = 'S-1-5-32-545'
$AuthenticatedUsersSid = 'S-1-5-11'

$ModifyMask = [int][Security.AccessControl.FileSystemRights]::Modify
$WriteMask = [int](
    [Security.AccessControl.FileSystemRights]::WriteData -bor
    [Security.AccessControl.FileSystemRights]::AppendData -bor
    [Security.AccessControl.FileSystemRights]::WriteAttributes -bor
    [Security.AccessControl.FileSystemRights]::WriteExtendedAttributes -bor
    [Security.AccessControl.FileSystemRights]::CreateFiles -bor
    [Security.AccessControl.FileSystemRights]::CreateDirectories -bor
    [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles -bor
    [Security.AccessControl.FileSystemRights]::Delete -bor
    [Security.AccessControl.FileSystemRights]::ChangePermissions -bor
    [Security.AccessControl.FileSystemRights]::TakeOwnership
)

$script:Failures = [Collections.Generic.List[string]]::new()
$script:Unverified = [Collections.Generic.List[string]]::new()
$script:Observations = [Collections.Generic.List[string]]::new()
$script:VerifiedChecks = [Collections.Generic.List[string]]::new()

function Add-Failure([string]$Name, [string]$Detail) {
    $message = "$Name -- $Detail"
    $script:Failures.Add($message)
    Write-Output "FAIL: $message"
}

function Add-Unverified([string]$Name, [string]$Detail) {
    $message = "$Name -- $Detail"
    $script:Unverified.Add($message)
    Write-Output "NOT VERIFIED: $message"
}

function Add-Observation([string]$Name, [string]$Detail) {
    $message = "$Name -- $Detail"
    $script:Observations.Add($message)
    Write-Output "OBSERVED: $message"
}

function Add-Verified([string]$Name, [string]$Detail) {
    $message = "$Name -- $Detail"
    $script:VerifiedChecks.Add($message)
    Write-Output "VERIFIED: $message"
}

function Get-SidValue($IdentityReference) {
    return $IdentityReference.Translate(
        [Security.Principal.SecurityIdentifier]
    ).Value
}

function ConvertTo-DaclSddl([string]$Sddl) {
    $descriptor = [Security.AccessControl.RawSecurityDescriptor]::new($Sddl)
    if ($null -eq $descriptor.DiscretionaryAcl) {
        throw 'DACL is absent or unsupported by RawSecurityDescriptor'
    }

    return $descriptor.GetSddlForm(
        [Security.AccessControl.AccessControlSections]::Access
    )
}

function Get-RelativePath([string]$Base, [string]$Full) {
    $basePath = [IO.Path]::GetFullPath($Base).TrimEnd('\') + '\'
    $fullPath = [IO.Path]::GetFullPath($Full)

    if (-not $fullPath.StartsWith(
        $basePath,
        [StringComparison]::OrdinalIgnoreCase
    )) {
        throw "Path is outside expected parent '$Base': '$Full'"
    }

    return $fullPath.Substring($basePath.Length).Replace('/', '\')
}

function Assert-TargetRelativePath($Target, [string]$Path) {
    if ([string]::IsNullOrWhiteSpace($Path) -or
        [IO.Path]::IsPathRooted($Path) -or
        $Path.Contains(':')) {
        throw "Empty, rooted, or invalid relative path: '$Path'"
    }

    $parts = $Path -split '[\\/]'
    if ($parts.Count -eq 0 -or
        $parts[0] -ine $Target.RelativeRoot -or
        @($parts | Where-Object {
            $_ -eq '' -or $_ -eq '.' -or $_ -eq '..'
        }).Count -gt 0) {
        throw "Wrong target root or traversal component: '$Path'"
    }

    $resolved = [IO.Path]::GetFullPath(
        (Join-Path $Target.WorkingDirectory $Path)
    )
    $root = [IO.Path]::GetFullPath($Target.FullRoot).TrimEnd('\')

    if (-not (
        [string]::Equals(
            $resolved.TrimEnd('\'),
            $root,
            [StringComparison]::OrdinalIgnoreCase
        ) -or
        $resolved.StartsWith(
            $root + '\',
            [StringComparison]::OrdinalIgnoreCase
        )
    )) {
        throw "Resolved path escapes target root: '$Path'"
    }
}

function Get-DirectoryAclRules($Acl) {
    return @($Acl.GetAccessRules(
        $true,
        $true,
        [Security.Principal.SecurityIdentifier]
    ))
}

function Get-TreeSnapshot($Target) {
    $snapshot = [pscustomobject]@{
        Name = $Target.Name
        RootPath = $Target.FullRoot
        Map = [Collections.Generic.Dictionary[string,string]]::new(
            [StringComparer]::Ordinal
        )
        CasePaths = [Collections.Generic.HashSet[string]]::new(
            [StringComparer]::OrdinalIgnoreCase
        )
        RootSddl = $null
        RootRules = @()
        AuthenticatedUsersAces = [Collections.Generic.List[object]]::new()
        ReparsePoints = [Collections.Generic.List[string]]::new()
        EnumerationErrors = [Collections.Generic.List[string]]::new()
        InspectionErrors = [Collections.Generic.List[string]]::new()
        DiscoveredObjects = 0
        InspectedObjects = 0
        RootInspected = $false
        IsComplete = $false
    }

    try {
        $rootItem = Get-Item -LiteralPath $Target.FullRoot -Force -ErrorAction Stop
    }
    catch {
        throw "Target root missing or inaccessible '$($Target.FullRoot)': $($_.Exception.Message)"
    }

    if (-not $rootItem.PSIsContainer) {
        throw "Target root is not a directory: $($Target.FullRoot)"
    }
    if ($rootItem.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw "Target root is a reparse point and was not traversed: $($Target.FullRoot)"
    }

    $stack = [Collections.Generic.Stack[IO.DirectoryInfo]]::new()
    $stack.Push([IO.DirectoryInfo]$rootItem)

    while ($stack.Count -gt 0) {
        $directory = $stack.Pop()

        try {
            $children = @(Get-ChildItem -LiteralPath $directory.FullName -Force -ErrorAction Stop)
        }
        catch {
            $snapshot.EnumerationErrors.Add(
                "Directory enumeration failed '$($directory.FullName)': $($_.Exception.Message)"
            )
            continue
        }

        foreach ($item in $children) {
            $snapshot.DiscoveredObjects++

            if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
                $snapshot.ReparsePoints.Add($item.FullName)
                continue
            }

            try {
                $relative = Get-RelativePath $Target.WorkingDirectory $item.FullName
                Assert-TargetRelativePath $Target $relative

                if (-not $snapshot.CasePaths.Add($relative)) {
                    throw "Duplicate or case-colliding path '$relative'"
                }

                # Enqueue validated non-reparse directories before ACL inspection,
                # so a Get-Acl failure does not hide their descendants.
                if ($item.PSIsContainer) {
                    $stack.Push([IO.DirectoryInfo]$item)
                }

                $acl = Get-Acl -LiteralPath $item.FullName -ErrorAction Stop
                $sddl = $acl.GetSecurityDescriptorSddlForm(
                    [Security.AccessControl.AccessControlSections]::Access
                )
                $daclSddl = ConvertTo-DaclSddl $sddl

                $snapshot.Map.Add($relative, $daclSddl)
                $snapshot.InspectedObjects++

                $rules = Get-DirectoryAclRules $acl
                foreach ($rule in $rules) {
                    if ((Get-SidValue $rule.IdentityReference) -eq $AuthenticatedUsersSid) {
                        $rights = [int]$rule.FileSystemRights
                        $snapshot.AuthenticatedUsersAces.Add([pscustomobject]@{
                            Path = $item.FullName
                            AccessType = [string]$rule.AccessControlType
                            Rights = [string]$rule.FileSystemRights
                            RightsMask = $rights
                            HasModifyMask = (($rights -band $ModifyMask) -eq $ModifyMask)
                            HasWriteCapableBits = (($rights -band $WriteMask) -ne 0)
                            InheritanceFlags = [string]$rule.InheritanceFlags
                            PropagationFlags = [string]$rule.PropagationFlags
                            IsInherited = [bool]$rule.IsInherited
                        })
                    }
                }
            }
            catch {
                $snapshot.InspectionErrors.Add(
                    "Object inspection failed '$($item.FullName)': $($_.Exception.Message)"
                )
            }
        }
    }

    try {
        $rootRelative = Get-RelativePath $Target.WorkingDirectory $Target.FullRoot
        Assert-TargetRelativePath $Target $rootRelative

        if (-not $snapshot.CasePaths.Add($rootRelative)) {
            throw "Duplicate target-root path '$rootRelative'"
        }

        $rootAcl = Get-Acl -LiteralPath $Target.FullRoot -ErrorAction Stop
        $rootSddl = $rootAcl.GetSecurityDescriptorSddlForm(
            [Security.AccessControl.AccessControlSections]::Access
        )

        $snapshot.RootSddl = ConvertTo-DaclSddl $rootSddl
        $snapshot.RootRules = Get-DirectoryAclRules $rootAcl
        $snapshot.Map.Add($rootRelative, $snapshot.RootSddl)
        $snapshot.InspectedObjects++
        $snapshot.RootInspected = $true

        foreach ($rule in $snapshot.RootRules) {
            if ((Get-SidValue $rule.IdentityReference) -eq $AuthenticatedUsersSid) {
                $rights = [int]$rule.FileSystemRights
                $snapshot.AuthenticatedUsersAces.Add([pscustomobject]@{
                    Path = $Target.FullRoot
                    AccessType = [string]$rule.AccessControlType
                    Rights = [string]$rule.FileSystemRights
                    RightsMask = $rights
                    HasModifyMask = (($rights -band $ModifyMask) -eq $ModifyMask)
                    HasWriteCapableBits = (($rights -band $WriteMask) -ne 0)
                    InheritanceFlags = [string]$rule.InheritanceFlags
                    PropagationFlags = [string]$rule.PropagationFlags
                    IsInherited = [bool]$rule.IsInherited
                })
            }
        }
    }
    catch {
        $snapshot.InspectionErrors.Add(
            "Root DACL inspection failed '$($Target.FullRoot)': $($_.Exception.Message)"
        )
    }

    $expectedReachableCount = 1 + $snapshot.DiscoveredObjects - $snapshot.ReparsePoints.Count
    $countsMatch = ($snapshot.Map.Count -eq $expectedReachableCount)

    if (-not $countsMatch) {
        $snapshot.InspectionErrors.Add(
            "Snapshot count mismatch: expected $expectedReachableCount DACL records for the target root plus observed non-reparse objects; recorded $($snapshot.Map.Count). This count does not establish that the entire filesystem tree was discovered."
        )
    }

    $snapshot.IsComplete = (
        $snapshot.RootInspected -and
        $snapshot.EnumerationErrors.Count -eq 0 -and
        $snapshot.InspectionErrors.Count -eq 0 -and
        $snapshot.ReparsePoints.Count -eq 0 -and
        $countsMatch
    )

    return $snapshot
}

function Assert-MapsEqual($Actual, $Expected, [string]$Label) {
    if ($Actual.Count -ne $Expected.Count) {
        throw "$Label path count differs: expected $($Expected.Count), got $($Actual.Count)"
    }

    foreach ($path in $Expected.Keys) {
        if (-not $Actual.ContainsKey($path)) {
            throw "$Label missing or case-mismatched path '$path'"
        }
        if ($Actual[$path] -cne $Expected[$path]) {
            throw "$Label DACL serialization differs at '$path'"
        }
    }

    foreach ($path in $Actual.Keys) {
        if (-not $Expected.ContainsKey($path)) {
            throw "$Label unexpected path '$path'"
        }
    }
}

function Report-RequiredRootRights($Target, $Rules) {
    # This is a bitwise union of matching root Allow ACE masks only.
    # It is a DACL observation, not a Windows effective-access calculation.
    $requirements = @(
        @{
            Name = 'SYSTEM Full Control'
            Sid = $SystemSid
            Rights = [int][Security.AccessControl.FileSystemRights]::FullControl
        },
        @{
            Name = 'Administrators Full Control'
            Sid = $AdministratorsSid
            Rights = [int][Security.AccessControl.FileSystemRights]::FullControl
        },
        @{
            Name = 'Users Read & Execute'
            Sid = $UsersSid
            Rights = [int][Security.AccessControl.FileSystemRights]::ReadAndExecute
        }
    )

    foreach ($need in $requirements) {
        $allowRules = @($Rules | Where-Object {
            $_.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            (Get-SidValue $_.IdentityReference) -eq $need.Sid
        })

        $combined = 0
        foreach ($rule in $allowRules) {
            $combined = $combined -bor ([int]$rule.FileSystemRights)
        }

        $covered = (($combined -band $need.Rights) -eq $need.Rights)
        if ($covered) {
            Add-Observation "$($Target.Name): root $($need.Name)" `
                'The union of matching root Allow ACE masks contains the requested rights; this says nothing about other tree objects or effective access'
        }
        else {
            Add-Observation "$($Target.Name): root $($need.Name)" `
                'The requested rights were not observed in the union of matching root Allow ACE masks; this is not an effective-access calculation'
        }
    }
}

function Report-SnapshotErrors($Target, $Snapshot, [string]$Label) {
    foreach ($errorText in $Snapshot.EnumerationErrors) {
        Add-Failure "$($Target.Name): $Label enumeration" $errorText
    }

    foreach ($errorText in $Snapshot.InspectionErrors) {
        Add-Failure "$($Target.Name): $Label DACL inspection" $errorText
    }
}

function Report-Target($Target) {
    Write-Output ''
    Write-Output "=== $($Target.Name): $($Target.FullRoot) ==="

    $first = $null
    try {
        $first = Get-TreeSnapshot $Target
    }
    catch {
        Add-Failure "$($Target.Name): initial snapshot" $_.Exception.Message
        Add-Unverified "$($Target.Name): whole-tree DACL completeness" `
            'No initial snapshot was returned'
        return
    }

    Add-Observation "$($Target.Name): first-snapshot inspected objects" ([string]$first.InspectedObjects)
    Add-Observation "$($Target.Name): first-snapshot discovered child objects" ([string]$first.DiscoveredObjects)
    Add-Observation "$($Target.Name): first-snapshot excluded reparse points" ([string]$first.ReparsePoints.Count)

    if ($first.RootInspected) {
        Add-Observation "$($Target.Name): root DACL (Access-section SDDL)" $first.RootSddl

        $explicitCount = @($first.RootRules | Where-Object { -not $_.IsInherited }).Count
        $inheritedCount = @($first.RootRules | Where-Object { $_.IsInherited }).Count
        Add-Observation "$($Target.Name): root ACE counts" `
            "explicit=$explicitCount; inherited=$inheritedCount"

        Report-RequiredRootRights $Target $first.RootRules
    }
    else {
        Add-Unverified "$($Target.Name): root DACL observations" 'Root DACL was not available'
    }

    if ($first.ReparsePoints.Count -gt 0) {
        foreach ($path in $first.ReparsePoints) {
            Add-Unverified "$($Target.Name): excluded reparse point" "$path was not traversed or inspected"
        }
    }

    if ($first.AuthenticatedUsersAces.Count -eq 0) {
        Add-Observation "$($Target.Name): Authenticated Users ACEs" `
            'None observed among objects whose DACLs were inspected'
    }
    else {
        Write-Output 'Authenticated Users ACEs (DACL observations):'
        foreach ($ace in $first.AuthenticatedUsersAces) {
            $aceNote = if ($ace.HasModifyMask) {
                'MODIFY RIGHTS MASK OBSERVED'
            }
            elseif ($ace.HasWriteCapableBits) {
                'WRITE-CAPABLE RIGHTS BITS OBSERVED'
            }
            else {
                'NO TESTED WRITE-CAPABLE BITS OBSERVED'
            }

            Write-Output (
                '  {0}: Type={1}; Rights={2}; Inheritance={3}; Propagation={4}; Inherited={5}; {6}' -f `
                $ace.Path, $ace.AccessType, $ace.Rights, $ace.InheritanceFlags,
                $ace.PropagationFlags, $ace.IsInherited, $aceNote
            )
        }
    }

    Report-SnapshotErrors $Target $first 'first snapshot'

    if ($first.IsComplete) {
        Add-Verified "$($Target.Name): reachable-object DACL scan completeness" `
            'Every reachable non-reparse object in this scan had a DACL inspected; this does not establish that the DACLs are secure or approved'
    }
    else {
        Add-Unverified "$($Target.Name): reachable-object DACL scan completeness" `
            'The snapshot had errors, excluded reparse points, or a count mismatch; the whole-tree DACL scan is incomplete'
    }

    $second = $null
    try {
        $second = Get-TreeSnapshot $Target
    }
    catch {
        Add-Failure "$($Target.Name): second snapshot" $_.Exception.Message
        Add-Unverified "$($Target.Name): repeated-snapshot comparison" 'Second snapshot was not returned'
    }

    if ($null -ne $second) {
        Add-Observation "$($Target.Name): second-snapshot inspected objects" ([string]$second.InspectedObjects)
        Add-Observation "$($Target.Name): second-snapshot excluded reparse points" ([string]$second.ReparsePoints.Count)
        Report-SnapshotErrors $Target $second 'second snapshot'

        if (-not $first.IsComplete -or -not $second.IsComplete) {
            Add-Unverified "$($Target.Name): repeated-snapshot comparison" `
                'At least one snapshot was incomplete; a whole-tree comparison is not reported'
        }
        else {
            try {
                Assert-MapsEqual $second.Map $first.Map "$($Target.Name) repeated snapshot"
                Add-Observation "$($Target.Name): repeated DACL snapshots" `
                    'The two complete scan results had matching path sets and serialized DACLs; this does not prove there were no intervening changes'
            }
            catch {
                Add-Failure "$($Target.Name): repeated DACL snapshots" $_.Exception.Message
            }
        }
    }

    if ([string]::IsNullOrWhiteSpace($ApprovedBaselineSha256[$Target.Name])) {
        Add-Unverified "$($Target.Name): baseline approval" `
            'No independently approved baseline hash is set or validated'
    }
    else {
        Add-Unverified "$($Target.Name): baseline approval" `
            'A value is present but this audit does not authenticate its approval or validate it'
    }

    if (@($ApprovedRemoveAceManifest[$Target.Name]).Count -eq 0) {
        Add-Unverified "$($Target.Name): ACE manifest approval" `
            'No approved removal manifest is set or validated'
    }
    else {
        Add-Unverified "$($Target.Name): ACE manifest approval" `
            'A value is present but this audit does not authenticate its approval or validate it'
    }

    if ([string]::IsNullOrWhiteSpace($BackupPaths[$Target.Name])) {
        Add-Unverified "$($Target.Name): backup validation" `
            'No backup path is set; this audit does not create or validate backups'
    }
    else {
        Add-Unverified "$($Target.Name): backup validation" `
            'A value is present but this audit does not validate backup identity or contents'
    }

    Add-Unverified "$($Target.Name): effective access" `
        'ACE inspection and bitwise Allow-mask union are not Windows effective-access calculations'
}

if (-not $ReadOnlyValidationMode -or $CreateBackups -or $ApplyAclChanges) {
    throw 'Audit refuses to run unless read-only mode is true and backup and ACL-change switches are false'
}

Write-Output 'Scope: DACL observations only. This script does not verify owners, SACLs, effective access, backups, or baseline approval.'
Write-Output 'Status meanings: VERIFIED = the specifically named read-only scan check passed; OBSERVED = DACL evidence without an approval or effective-access conclusion; FAIL = an inspection or structural check failed; NOT VERIFIED = evidence is missing or the scan is incomplete.'
Write-Output 'A VERIFIED reachable-object DACL scan completeness result is not a claim that the target is secure, compliant, or approved.'

foreach ($target in $Targets) {
    Report-Target $target
}

Write-Output ''
Write-Output '=== SUMMARY ==='
Write-Output "Verified scan checks: $($script:VerifiedChecks.Count)"
Write-Output "DACL observations: $($script:Observations.Count)"
Write-Output "Failures: $($script:Failures.Count)"
Write-Output "Unverified checks: $($script:Unverified.Count)"

if ($script:Failures.Count -gt 0) {
    Write-Output 'OVERALL: FAIL'
    exit 1
}

Write-Output 'OVERALL: NOT VERIFIED'
exit 2
