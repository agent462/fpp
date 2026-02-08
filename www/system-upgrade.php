<!DOCTYPE html>
<html>

<head>
    <?php
    require_once 'config.php';
    require_once 'common.php';

    include 'common/menuHead.inc';
    ?>
    <link rel="stylesheet" href="css/fpp-system-design.css?ref=<?php echo filemtime('css/fpp-system-design.css'); ?>">
    <?php
    $fppVersion = getFPPVersion();
    $localGitVersion = get_local_git_version();
    $remoteGitVersion = get_remote_git_version();

    $uploadDirectory = $mediaDirectory . "/upload";
    $freeSpace = disk_free_space($uploadDirectory);
    $osUpdateFiles = getFileList($uploadDirectory, "fppos");

    if (file_exists("/proc/cpuinfo")) {
        $serialNumber = exec("sed -n 's/^Serial.*: //p' /proc/cpuinfo", $output, $return_val);
        if ($return_val != 0) {
            unset($serialNumber);
        }
    }
    if ((!isset($serialNumber) || $serialNumber == "") && $settings['Variant'] == "PocketBeagle2") {
        $serialNumber = exec("dd if=/sys/bus/i2c/devices/0-0050/eeprom count=16 skip=40 bs=1 2>/dev/null", $output, $return_val);
        if ($return_val != 0) {
            unset($serialNumber);
        }
    }
    unset($output);
    ?>
    <style>
        .faq-accordion .accordion-button {
            font-size: 0.85em;
            padding: 0.6rem 0.85rem;
            background-color: #fff;
            border: none;
            font-weight: 600;
            color: #333;
            position: relative;
            padding-right: 2.5rem;
        }

        .faq-accordion .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            color: #000;
            box-shadow: none;
        }

        .faq-accordion .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0, 0, 0, .125);
        }

        .faq-accordion .accordion-button::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 0.85rem;
            transition: transform 0.2s ease-in-out;
            font-size: 0.75em;
        }

        .faq-accordion .accordion-button:not(.collapsed)::after {
            transform: rotate(180deg);
        }

        .faq-accordion .accordion-body {
            font-size: 0.85em;
            padding: 0.85rem 1rem;
            line-height: 1.5;
            background-color: #f8f9fa;
            color: #555;
        }

        .faq-accordion .accordion-item {
            margin-bottom: 0.35rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            overflow: hidden;
        }

        .faq-accordion .accordion-item:last-child {
            margin-bottom: 0;
        }

        .resources-card {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }

        .resources-card h3 {
            margin-bottom: 15px;
        }

        .resources-card ul {
            list-style: none;
            padding-left: 0;
        }

        .resources-card ul li {
            padding: 5px 0;
        }

        .resources-card ul li i {
            width: 20px;
            text-align: center;
        }

        .comparison-table {
            margin: 30px 0;
        }

        .comparison-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .comparison-table th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }

        .comparison-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .comparison-table tr:last-child td {
            border-bottom: none;
        }

        .comparison-table .feature-name {
            font-weight: bold;
        }

    </style>
    <script>
        var osAssetMap = {};

        function OpenChangelogModal() {
            DoModalDialog({
                id: 'changelogModal',
                title: 'FPP Changelog',
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading changelog...</div>',
                class: 'modal-xl',
                keyboard: true,
                backdrop: true
            });

            $.get('api/git/originLog', function (data) {
                var html = '<div style="max-height: 70vh; overflow-y: auto;">';
                html += '<p class="alert alert-info"><i class="fas fa-info-circle"></i> This shows the git commit history for your current branch. Each entry represents a change to the FPP software.</p>';
                html += '<table class="table table-striped table-sm">';
                html += '<thead><tr><th width="100px">Commit</th><th width="150px">Author</th><th width="150px">Date</th><th>Message</th></tr></thead>';
                html += '<tbody>';

                if (data.rows && data.rows.length > 0) {
                    data.rows.forEach(function (row) {
                        html += '<tr>';
                        html += '<td><code>' + row.hash.substring(0, 8) + '</code></td>';
                        html += '<td>' + row.author + '</td>';
                        html += '<td style="font-size: 0.85em; color: #666;">' + (row.date || '') + '</td>';
                        html += '<td>' + row.msg + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="4" class="text-center">No changelog entries found</td></tr>';
                }

                html += '</tbody></table>';
                html += '<div class="text-center mt-3">';
                html += '<a href="https://github.com/FalconChristmas/fpp/commits" target="_blank" class="btn btn-outline-primary">';
                html += '<i class="fas fa-external-link-alt"></i> View Full History on GitHub';
                html += '</a>';
                html += '</div>';
                html += '</div>';

                $('#changelogModal .modal-body').html(html);
            }).fail(function () {
                $('#changelogModal .modal-body').html('<div class="alert alert-danger">Failed to load changelog</div>');
            });
        }

        function ViewOSReleaseNotes() {
            var osVersion = $('#osSelect option:selected').text();

            if (!osVersion || osVersion == '-- Choose an OS Version --') {
                DialogError('No OS Selected', 'Please select an OS version first to view its release notes.');
                return;
            }

            DoModalDialog({
                id: 'osReleaseNotesModal',
                title: 'OS Release Notes: ' + osVersion,
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading release notes...</div>',
                class: 'modal-lg',
                keyboard: true,
                backdrop: true
            });

            // Extract version tag from OS filename (e.g., FPP-v9.0-Pi.img -> v9.0)
            var versionMatch = osVersion.match(/v(\d+\.\d+(?:\.\d+)?(?:-[a-z]+\d*)?)/);
            if (!versionMatch) {
                $('#osReleaseNotesModal .modal-body').html('<div class="alert alert-warning">Could not determine version number from selected OS.</div>');
                return;
            }

            var version = 'v' + versionMatch[1];

            // Fetch release notes from GitHub API
            $.ajax({
                url: 'https://api.github.com/repos/FalconChristmas/fpp/releases/tags/' + version,
                dataType: 'json',
                success: function (release) {
                    var html = '<div style="max-height: 70vh; overflow-y: auto;">';

                    if (release.name) {
                        html += '<h4>' + release.name + '</h4>';
                    }

                    if (release.published_at) {
                        var date = new Date(release.published_at);
                        html += '<p class="text-muted"><i class="fas fa-calendar"></i> Published: ' + date.toLocaleDateString() + '</p>';
                    }

                    if (release.body) {
                        // Convert markdown to HTML (basic conversion)
                        var body = release.body
                            .replace(/#{3} (.+)/g, '<h5>$1</h5>')
                            .replace(/#{2} (.+)/g, '<h4>$1</h4>')
                            .replace(/# (.+)/g, '<h3>$1</h3>')
                            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.+?)\*/g, '<em>$1</em>')
                            .replace(/^- (.+)/gm, '<li>$1</li>')
                            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
                            .replace(/\n\n/g, '</p><p>')
                            .replace(/^(?!<[hul])/gm, '<p>')
                            .replace(/(?<![>])$/gm, '</p>');

                        html += '<div class="release-notes-body">' + body + '</div>';
                    } else {
                        html += '<p class="text-muted">No release notes available for this version.</p>';
                    }

                    html += '<div class="mt-3">';
                    html += '<a href="' + release.html_url + '" target="_blank" class="btn btn-outline-primary">';
                    html += '<i class="fas fa-external-link-alt"></i> View Full Release on GitHub';
                    html += '</a>';
                    html += '</div>';
                    html += '</div>';

                    $('#osReleaseNotesModal .modal-body').html(html);
                },
                error: function () {
                    var html = '<div class="alert alert-warning">';
                    html += '<i class="fas fa-info-circle"></i> Release notes not found for version ' + version + '.';
                    html += '<br><br>This may be because:';
                    html += '<ul><li>The version hasn\'t been officially released yet</li>';
                    html += '<li>It\'s a development or beta build</li>';
                    html += '<li>The release notes are not available on GitHub</li></ul>';
                    html += '<a href="https://github.com/FalconChristmas/fpp/releases" target="_blank" class="btn btn-outline-primary mt-2">';
                    html += '<i class="fas fa-external-link-alt"></i> Browse All Releases on GitHub';
                    html += '</a>';
                    html += '</div>';
                    $('#osReleaseNotesModal .modal-body').html(html);
                }
            });
        }

        function GetGitOriginLog() {
            DoModalDialog({
                id: 'gitOriginLogModal',
                title: 'Pending Git Changes',
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading changes...</div>',
                class: 'modal-lg',
                keyboard: true,
                backdrop: true
            });

            $.get('api/git/originLog', function (data) {
                var html = '<table class="table table-striped table-sm"><thead><tr><th width="80px">Commit</th><th width="150px">Author</th><th>Message</th></tr></thead><tbody>';
                if (data.rows && data.rows.length > 0) {
                    data.rows.forEach(function (row) {
                        html += '<tr>';
                        html += '<td><code>' + row.hash.substring(0, 8) + '</code></td>';
                        html += '<td>' + row.author + '</td>';
                        html += '<td>' + row.msg + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<tr><td colspan="3" class="text-center">';
                    html += '<div class="alert alert-info mb-0">';
                    html += '<i class="fas fa-info-circle"></i> ';
                    html += 'Unable to determine pending changes. This may occur when working with feature branches or forks. ';
                    html += 'You can still update to get the latest changes using the "Update FPP Now" button.';
                    html += '</div>';
                    html += '</td></tr>';
                    html += '</tbody></table>';
                }
                $('#gitOriginLogModal .modal-body').html(html);
            }).fail(function () {
                $('#gitOriginLogModal .modal-body').html('<div class="alert alert-danger">Failed to load git changes</div>');
            });
        }

        function UpdateVersionInfo() {
            $.get('api/system/status', function (data) {
                if (data.advancedView) {
                    if (data.advancedView.Version) {
                        $('#fppVersionValue').text(data.advancedView.Version);
                    }
                    if (data.advancedView.Platform) {
                        $('#platformValue').text(data.advancedView.Platform);
                    }
                    if (data.advancedView.Variant) {
                        $('#variantValue').text(data.advancedView.Variant);
                    }
                    if (data.advancedView.Mode) {
                        $('#modeValue').text(data.advancedView.Mode);
                    }
                    if (data.advancedView.HostName) {
                        $('#hostnameValue').text(data.advancedView.HostName);
                    }
                    if (data.advancedView.HostDescription) {
                        $('#hostDescValue').text(data.advancedView.HostDescription);
                    }
                    if (data.advancedView.OSVersion) {
                        $('#osVersionValue').text(data.advancedView.OSVersion);
                    }
                    if (data.advancedView.OSRelease) {
                        $('#osReleaseValue').text(data.advancedView.OSRelease);
                    }
                    if (data.advancedView.Kernel) {
                        $('#kernelValue').text(data.advancedView.Kernel);
                    }

                    var localVer = data.advancedView.LocalGitVersion;
                    var remoteVer = data.advancedView.RemoteGitVersion;

                    if (data.advancedView.LocalGitVersion) {
                        $('#localGitValue').text(data.advancedView.LocalGitVersion);
                        $('#localGitShort').text(data.advancedView.LocalGitVersion);
                    }

                    if (remoteVer && remoteVer !== "Unknown" && remoteVer !== "" && remoteVer !== localVer) {
                        // Update available state
                        $('#remoteGitShort').text(remoteVer);
                        $('#gitUpdateBadge').show();
                        $('#fppVersionIndicator').show();
                        $('#fppVersionCurrent').hide();
                        $('#fppUpdateBanner').show();
                        $('#fppVersionStatusBadge').removeClass('fpp-badge--neutral fpp-badge--success').addClass('fpp-badge--warning').text('Update Available');

                        // Fetch commit count from git origin log
                        $.get('api/git/originLog', function (gitData) {
                            if (gitData.rows && gitData.rows.length > 0) {
                                $('#commitCount').text(gitData.rows.length);
                            }
                        });
                    } else {
                        // Up to date state
                        $('#gitUpdateBadge').hide();
                        $('#fppVersionIndicator').hide();
                        $('#fppVersionCurrent').show();
                        $('#fppUpdateBanner').hide();
                        $('#fppVersionStatusBadge').removeClass('fpp-badge--neutral fpp-badge--warning').addClass('fpp-badge--success').text('Up to Date');
                    }

                    // Show OS version badge
                    $('#osVersionStatusBadge').show();
                }
            });
        }

        function UpgradeFPP() {
            var options = {
                id: "upgradePopupStatus",
                title: "FPP Upgrade",
                body: "<textarea style='max-width:100%; max-height:100%; width: 100%; height:100%;' disabled id='streamedUpgradeText'></textarea>",
                class: "modal-dialog-scrollable",
                noClose: true,
                keyboard: false,
                backdrop: "static",
                footer: "",
                buttons: {
                    "Close": {
                        id: 'fppUpgradeCloseDialogButton',
                        click: function () {
                            CloseFPPUpgradeDialog();
                        },
                        disabled: true,
                        class: 'btn-success'
                    }
                }
            };
            $("#fppUpgradeCloseDialogButton").prop("disabled", true);
            DoModalDialog(options);
            StreamURL('manualUpdate.php?wrapped=1', 'streamedUpgradeText', 'FPPUpgradeDone');
        }

        function FPPUpgradeDone() {
            $('#fppUpgradeCloseDialogButton').prop("disabled", false);
            EnableModalDialogCloseButton("upgradePopupStatus");
            UpdateVersionInfo();
        }

        function CloseFPPUpgradeDialog() {
            CloseModalDialog('upgradePopupStatus');
            location.reload();
        }

        function PopulateOSSelect() {
            <? if ($freeSpace > 1000000000) { ?>

                var allPlatforms = '';
                if ($('#allPlatforms').is(':checked')) {
                    allPlatforms = 'api/git/releases/os/all';
                } else {
                    allPlatforms = 'api/git/releases/os';
                }

                //cleanup previous load values
                $('#osSelect option').filter(function () { return parseInt(this.value) > 0; }).remove();

                $.get(allPlatforms, function (data) {
                    var devMode = (settings['uiLevel'] && (parseInt(settings['uiLevel']) == 3));
                    var showLegacy = $('#LegacyOS').is(':checked');
                    // Regex to match versions below 9.0 (With N-1 - update this yearly)
                    var legacyVersionRegex = /[-_]v?[0-8]\./i;

                    // Show/hide legacy OS warning
                    if (showLegacy) {
                        $('#legacyOSWarning').show();
                    } else {
                        $('#legacyOSWarning').hide();
                    }

                    if ("files" in data) {
                        for (const file of data["files"]) {
                            osAssetMap[file["asset_id"]] = {
                                name: file["filename"],
                                url: file["url"]
                            };

                            // Skip versions below 9.0 unless Legacy checkbox is checked or dev mode
                            var isLegacyVersion = legacyVersionRegex.test(file['filename']);
                            if (isLegacyVersion && !showLegacy && !devMode) {
                                continue;
                            }

                            if (!file["downloaded"]) {
                                $('#osSelect').append($('<option>', {
                                    value: file["asset_id"],
                                    text: file["filename"] + " (download)"
                                }));
                            }
                            if (file["downloaded"]) {
                                $('#osSelect').append($('<option>', {
                                    value: file["asset_id"],
                                    text: file["filename"]
                                }));
                            }
                        }
                    }

                    //only show alpha and beta images in Advanced ui
                    if (settings['uiLevel'] && (parseInt(settings['uiLevel']) >= 1)) {
                        //leave all avail options in place
                    } else {
                        $('#osSelect option').filter(function () { return (/beta/i.test(this.text)); }).remove();
                        $('#osSelect option').filter(function () { return (/alpha/i.test(this.text)); }).remove();
                    }

                    //insert files already downloaded if we haven't got them from the git api call
                    var osUpdateFiles = <?php echo json_encode($osUpdateFiles); ?>;
                    var select = $('#osSelect');
                    osUpdateFiles.forEach(element => {
                        // Skip legacy versions for downloaded files too
                        var isLegacyVersion = legacyVersionRegex.test(element);
                        if (isLegacyVersion && !showLegacy && !devMode) {
                            return;
                        }
                        if (select.has('option:contains("' + element + '")').length == 0) {
                            $('#osSelect').append($('<option>', {
                                value: element,
                                text: element
                            }));
                        }
                    });
                });

            <? } ?>
        }

        function UpgradeOS() {
            var os = $('#osSelect').val();
            var osName = os;

            if (os == '') {
                DialogError('No OS Selected', 'Please select an OS version to upgrade to.');
                return;
            }
            if (os in osAssetMap) {
                osName = osAssetMap[os].name;
                os = osAssetMap[os].url;
            }

            //override file location from git to local if already downloaded
            if ($('#osSelect option:selected').text().toLowerCase().indexOf('(download)') === -1) {
                os = $('#osSelect option:selected').text();
                osName = $('#osSelect option:selected').text();
            }

            if (confirm('Upgrade the OS using ' + osName +
                '?\nThis can take a long time. It is also strongly recommended to run FPP backup first.')) {

                var options = {
                    id: "upgradeOSPopupStatus",
                    title: "FPP OS Upgrade",
                    body: "<textarea style='max-width:100%; max-height:100%; width: 100%; height:100%;' disabled id='streamedUpgradeOSText'></textarea>",
                    class: "modal-dialog-scrollable",
                    noClose: true,
                    keyboard: false,
                    backdrop: "static",
                    footer: "",
                    buttons: {
                        "Close": {
                            id: 'fppUpgradeOSCloseDialogButton',
                            click: function () {
                                CloseModalDialog("upgradeOSPopupStatus");
                                location.reload();
                            },
                            disabled: true,
                            class: 'btn-success'
                        }
                    }
                };
                $("#fppUpgradeOSCloseDialogButton").prop("disabled", true);
                DoModalDialog(options);

                StreamURL('upgradeOS.php?wrapped=1&os=' + os + keepOptFPP, 'streamedUpgradeOSText', 'UpgradeDone', 'UpgradeDone');
            }
        }

        function UpgradeDone() {
            $("#fppUpgradeOSCloseDialogButton").prop("disabled", false);
            EnableModalDialogCloseButton("upgradeOSPopupStatus");
            UpdateVersionInfo();
        }

        function DownloadOS() {
            var os = $('#osSelect').val();
            var osName = os;

            if (os == '')
                return;

            if (os in osAssetMap) {
                osName = osAssetMap[os].name;
                os = osAssetMap[os].url;

                var options = {
                    id: "downloadPopupStatus",
                    title: "FPP Download OS Image",
                    body: "<textarea style='max-width:100%; max-height:100%; width: 100%; height:100%;' disabled id='streamedUDownloadText'></textarea>",
                    class: "modal-dialog-scrollable",
                    noClose: true,
                    keyboard: false,
                    backdrop: "static",
                    footer: "",
                    buttons: {
                        "Close": {
                            id: 'fppDownloadCloseDialogButton',
                            click: function () {
                                CloseModalDialog("downloadPopupStatus");
                                location.reload();
                            },
                            disabled: true,
                            class: 'btn-success'
                        }
                    }
                };
                $("#fppDownloadCloseDialogButton").prop("disabled", true);
                DoModalDialog(options);

                StreamURL('upgradeOS.php?wrapped=1&downloadOnly=1&os=' + os, 'streamedUDownloadText', 'DownloadDone');
            } else {
                alert('This fppos image has already been downloaded.');
            }
        }

        function DownloadDone() {
            $("#fppDownloadCloseDialogButton").prop("disabled", false);
            EnableModalDialogCloseButton("downloadPopupStatus");
            PopulateOSSelect();
        }

        function ViewOSReleaseNotes() {
            var osVersion = $('#osSelect option:selected').text();

            if (!osVersion || osVersion == '-- Choose an OS Version --') {
                DialogError('No OS Selected', 'Please select an OS version first to view its release notes.');
                return;
            }

            DoModalDialog({
                id: 'osReleaseNotesModal',
                title: 'OS Release Notes: ' + osVersion,
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading release notes...</div>',
                class: 'modal-lg',
                keyboard: true,
                backdrop: true
            });

            // Extract version tag from OS filename (e.g., BBB-9.3_2025-11.fppos -> 9.3)
            var versionMatch = osVersion.match(/(v?\d+\.\d+(?:\.\d+)?(?:-[a-z]+\d*)?)/i);
            if (!versionMatch) {
                $('#osReleaseNotesModal .modal-body').html('<div class=\"alert alert-warning\">Could not determine version number from selected OS.</div>');
                return;
            }

            var version = versionMatch[1];
            // Remove 'v' prefix if present - GitHub tags use numeric version (e.g., 9.3, not v9.3)
            if (version.startsWith('v') || version.startsWith('V')) {
                version = version.substring(1);
            }

            // Fetch release notes from GitHub API
            $.ajax({
                url: 'https://api.github.com/repos/FalconChristmas/fpp/releases/tags/' + version,
                dataType: 'json',
                success: function (release) {
                    var html = '<div style=\"max-height: 70vh; overflow-y: auto;\">';

                    if (release.name) {
                        html += '<h4>' + release.name + '</h4>';
                    }

                    if (release.published_at) {
                        var date = new Date(release.published_at);
                        html += '<p class=\"text-muted\"><i class=\"fas fa-calendar\"></i> Published: ' + date.toLocaleDateString() + '</p>';
                    }

                    if (release.body) {
                        // Convert markdown to HTML
                        var body = release.body
                            // Escape HTML
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            // Headers
                            .replace(/^### (.+)$/gm, '<h5>$1</h5>')
                            .replace(/^## (.+)$/gm, '<h4>$1</h4>')
                            .replace(/^# (.+)$/gm, '<h3>$1</h3>')
                            // Bold
                            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                            .replace(/__(.+?)__/g, '<strong>$1</strong>')
                            // Italic
                            .replace(/\*(.+?)\*/g, '<em>$1</em>')
                            .replace(/_(.+?)_/g, '<em>$1</em>')
                            // Code blocks
                            .replace(/```(.+?)```/gs, '<pre><code>$1</code></pre>')
                            .replace(/`(.+?)`/g, '<code>$1</code>')
                            // Links
                            .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank">$1</a>')
                            // Line breaks and paragraphs
                            .replace(/\n\n/g, '</p><p>')
                            .replace(/\n/g, '<br>');

                        // Wrap lists in ul tags
                        body = body.replace(/(<br>)?- (.+?)(<br>|<\/p>)/g, function (match, br1, content, br2) {
                            return '<li>' + content + '</li>';
                        });
                        body = body.replace(/(<li>.*?<\/li>)+/g, function (match) {
                            return '<ul>' + match + '</ul>';
                        });

                        html += '<div class=\"release-notes-body\"><p>' + body + '</p></div>';
                    } else {
                        html += '<p class=\"text-muted\">No release notes available for this version.</p>';
                    }

                    html += '<div class=\"mt-3\">';
                    html += '<a href=\"' + release.html_url + '\" target=\"_blank\" class=\"btn btn-outline-primary\">';
                    html += '<i class=\"fas fa-external-link-alt\"></i> View Full Release on GitHub';
                    html += '</a>';
                    html += '</div>';
                    html += '</div>';

                    $('#osReleaseNotesModal .modal-body').html(html);
                },
                error: function () {
                    var html = '<div class=\"alert alert-warning\">';
                    html += '<i class=\"fas fa-info-circle\"></i> Release notes not found for version ' + version + '.';
                    html += '<br><br>This may be because:';
                    html += '<ul><li>The version hasn\'t been officially released yet</li>';
                    html += '<li>It\'s a development or beta build</li>';
                    html += '<li>The release notes are not available on GitHub</li></ul>';
                    html += '<a href=\"https://github.com/FalconChristmas/fpp/releases\" target=\"_blank\" class=\"btn btn-outline-primary mt-2\">';
                    html += '<i class=\"fas fa-external-link-alt\"></i> Browse All Releases on GitHub';
                    html += '</a>';
                    html += '</div>';
                    $('#osReleaseNotesModal .modal-body').html(html);
                }
            });
        }

        function OSSelectChanged() {
            var os = $('#osSelect').val();
            <?
            // we want at least a 200MB in order to be able to apply the fppos
            if ($freeSpace < 200000000) {
                echo "os = '';\n";
            } ?>
            if (os == '') {
                $('#osUpgradeButton').attr('disabled', 'disabled');
                $('#osDownloadButton').attr('disabled', 'disabled');
                $('#osReleaseNotesButton').attr('disabled', 'disabled');
            } else {
                $('#osUpgradeButton').removeAttr('disabled');
                $('#osReleaseNotesButton').removeAttr('disabled');
                if (os in osAssetMap) {
                    $('#osDownloadButton').removeAttr('disabled');
                } else {
                    $('#osDownloadButton').attr('disabled', 'disabled');
                }
            }
        }

        $(document).ready(function () {
            UpdateVersionInfo();
            PopulateOSSelect();
        });
    </script>
</head>

<body>
    <div id="bodyWrapper">
        <?php
        $activeParentMenuItem = 'status';
        include 'menu.inc';
        ?>
        <div class="mainContainer">
            <h1 class="title">System Upgrade</h1>
            <div class="pageContent">

                <!-- Update Banners (conditionally shown) -->
                <div id="fppUpdateBanner" class="fpp-banner fpp-banner--success" style="display: none;">
                    <div class="fpp-banner__icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="fpp-banner__content">
                        <div class="fpp-banner__title">FPP Software Update Available!</div>
                        <p class="fpp-banner__message">A new version of the FPP software is ready to install. Updates typically complete in under 5 minutes and keep all your settings.</p>
                    </div>
                </div>

                <div id="osUpdateBanner" class="fpp-banner fpp-banner--warning" style="display: none;">
                    <div class="fpp-banner__icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="fpp-banner__content">
                        <div class="fpp-banner__title">Operating System Upgrade Available</div>
                        <p class="fpp-banner__message">A major OS version is available. OS upgrades include security patches, new hardware support, and system improvements. Always backup first!</p>
                    </div>
                </div>

                <? if (!isset($settings['cape-info']) || !isset($settings['cape-info']['verifiedKeyId']) || ($settings['cape-info']['verifiedKeyId'] != 'fp')) { ?>
                    <div id="donateBanner" class="fpp-donate-banner">
                        <h3 class="fpp-donate-banner__title">
                            <i class="fas fa-heart"></i> Support FPP Development
                        </h3>
                        <p class="fpp-donate-banner__text">
                            Help support the continued development of the Falcon Player. Your donation
                            helps fund equipment, hosting, and countless hours of development.
                        </p>
                        <form action="https://www.paypal.com/donate" method="post" target="_top">
                            <input type="hidden" name="hosted_button_id" value="ASF9XYZ2V2F5G" />
                            <button type="submit" class="fpp-donate-btn" title="Donate to the Falcon Player">
                                <svg class="paypal-logo" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944 3.72a.77.77 0 0 1 .757-.629h6.578c2.182 0 3.91.558 5.143 1.66 1.233 1.1 1.677 2.65 1.321 4.612-.042.236-.09.473-.152.707a7.092 7.092 0 0 1-.906 2.326c-.402.627-.905 1.16-1.5 1.586-.596.426-1.297.756-2.09.986-.792.23-1.666.345-2.604.345h-1.58a.95.95 0 0 0-.938.803l-.692 4.39-.394 2.5a.641.641 0 0 1-.633.531h-.278zm11.461-14.02c-.014.084-.03.168-.048.254-.593 3.044-2.623 4.095-5.215 4.095h-1.32a.641.641 0 0 0-.633.543l-.676 4.282-.383 2.43a.336.336 0 0 0 .332.39h2.333a.564.564 0 0 0 .557-.476l.023-.12.441-2.8.028-.154a.564.564 0 0 1 .557-.476h.35c2.268 0 4.042-.921 4.561-3.585.217-1.113.105-2.042-.47-2.695a2.238 2.238 0 0 0-.637-.488z"/></svg>
                                Donate with PayPal
                            </button>
                            <img alt="" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" style="display:none;" />
                        </form>
                        <p class="fpp-donate-banner__footer">
                            <i class="fas fa-coffee"></i> It takes a lot of time, equipment, and coffee to power your shows!
                        </p>
                    </div>
                <? } ?>

                <!-- Upgrade Options -->
                <div class="row" style="display: flex; flex-wrap: wrap;">
                    <!-- FPP Software Update -->
                    <div class="col-md-6" style="display: flex;">
                        <div class="fpp-card fpp-card--accent fpp-card--accent-success" style="flex: 1; display: flex; flex-direction: column;">
                            <div class="fpp-card__header">
                                <div class="fpp-card__icon fpp-card__icon--success">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <div>
                                    <h3 class="fpp-card__title">
                                        Update FPP Software
                                        <span id="gitUpdateBadge" class="fpp-badge fpp-badge--success" style="display: none; font-size: 0.5em; padding: 2px 6px;">Update Available</span>
                                    </h3>
                                    <p class="fpp-card__subtitle">Get the latest bug fixes and features. This is safe and quick.</p>
                                </div>
                            </div>

                            <div class="fpp-info-grid">
                                <div class="fpp-info-box fpp-info-box--neutral">
                                    <div class="fpp-info-box__title"><i class="fas fa-question-circle"></i> When to use</div>
                                    <ul>
                                        <li>When "Update Available" badge shows</li>
                                        <li>For latest bug fixes &amp; features</li>
                                        <li>Regular maintenance updates</li>
                                    </ul>
                                </div>
                                <div class="fpp-info-box fpp-info-box--info">
                                    <div class="fpp-info-box__title"><i class="fas fa-info-circle"></i> What it does</div>
                                    <p>Downloads the latest code changes for your version and rebuilds FPP. Typically takes 2-5 minutes. No reboot required.</p>
                                </div>
                            </div>

                            <!-- Version upgrade indicator (update available) -->
                            <div id="fppVersionIndicator" class="fpp-version-indicator fpp-version-indicator--clickable" style="display: none;" onclick="GetGitOriginLog();" title="Click to preview changes">
                                <span class="fpp-version-indicator__from" id="localGitShort"><?= $localGitVersion ?></span>
                                <i class="fas fa-arrow-right fpp-version-indicator__arrow"></i>
                                <span class="fpp-version-indicator__to" id="remoteGitShort"></span>
                                <span class="fpp-version-indicator__label"><i class="fas fa-search"></i> <span id="commitCount">0</span> changes behind</span>
                            </div>

                            <!-- Version indicator (up to date) -->
                            <div id="fppVersionCurrent" class="fpp-version-indicator fpp-version-indicator--current" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span class="fpp-version-indicator__current" id="localGitValue"><?= $localGitVersion ?></span>
                                <span class="fpp-version-indicator__label">You're up to date!</span>
                            </div>

                            <div class="card-actions" style="display: flex; align-items: center; gap: var(--fpp-sp-md); flex-wrap: wrap; margin-top: auto; padding-top: var(--fpp-sp-lg);">
                                <button class="fpp-btn fpp-btn--success" onclick="UpgradeFPP();">
                                    <i class="fas fa-download"></i> Update FPP Now
                                </button>
                                <button class="fpp-btn fpp-btn--outline" onclick="OpenChangelogModal();">
                                    <i class="fas fa-list"></i> View Changelog
                                </button>
                                <?
                                if ($settings['uiLevel'] > 0) {
                                    $upgradeSources = array();
                                    $remotes = getKnownFPPSystems();

                                    if ($settings["Platform"] != "MacOS") {
                                        $IPs = explode("\n", trim(shell_exec("/sbin/ifconfig -a | cut -f1 | cut -f1 -d' ' | grep -v ^$ | grep -v lo | grep -v eth0:0 | grep -v usb | grep -v SoftAp | grep -v 'can.' | sed -e 's/://g' | while read iface ; do /sbin/ifconfig \$iface | grep 'inet ' | awk '{print \$2}'; done")));
                                    } else {
                                        $IPs = explode("\n", trim(shell_exec("/sbin/ifconfig -a | grep 'inet ' | awk '{print \$2}'")));
                                    }
                                    $found = 0;
                                    foreach ($remotes as $desc => $host) {
                                        if ((!in_array($host, $IPs)) && (!preg_match('/^169\.254\./', $host))) {
                                            $upgradeSources[$desc] = $host;
                                            if (isset($settings['UpgradeSource']) && ($settings['UpgradeSource'] == $host)) {
                                                $found = 1;
                                            }
                                        }
                                    }
                                    if (!$found && isset($settings['UpgradeSource']) && ($settings['UpgradeSource'] != 'github.com')) {
                                        $upgradeSources = array($settings['UpgradeSource'] . ' (Unreachable)' => $settings['UpgradeSource'], 'github.com' => 'github.com') + $upgradeSources;
                                    } else {
                                        $upgradeSources = array("github.com" => "github.com") + $upgradeSources;
                                    }
                                    ?>
                                    <div class="advanced-options" style="margin-left: auto; display: flex; align-items: center; gap: var(--fpp-sp-sm); font-size: var(--fpp-fs-sm); color: var(--fpp-text-muted);">
                                        <span class="fpp-badge fpp-badge--info">Adv</span>
                                        <span>Source:</span>
                                        <? PrintSettingSelect("FPP Upgrade Source", "UpgradeSource", 0, 0, "github.com", $upgradeSources); ?>
                                    </div>
                                <? } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Operating System Upgrade -->
                    <div class="col-md-6" style="display: flex;">
                        <div class="fpp-card fpp-card--accent fpp-card--accent-warning" style="flex: 1; display: flex; flex-direction: column;">
                            <div class="fpp-card__header">
                                <div class="fpp-card__icon fpp-card__icon--warning">
                                    <i class="fas fa-hdd"></i>
                                </div>
                                <div>
                                    <h3 class="fpp-card__title">Upgrade Operating System</h3>
                                    <p class="fpp-card__subtitle">Upgrade the entire FPP operating system with a new version</p>
                                </div>
                            </div>

                            <div class="fpp-info-grid">
                                <div class="fpp-info-box fpp-info-box--neutral">
                                    <div class="fpp-info-box__title"><i class="fas fa-question-circle"></i> When to use</div>
                                    <ul>
                                        <li>Moving to a new major version (e.g., v9 to v10)</li>
                                        <li>Release notes specifically recommend it</li>
                                        <li>Experiencing OS issues</li>
                                        <li>Applying latest OS security patches</li>
                                    </ul>
                                </div>
                                <div class="fpp-info-box fpp-info-box--info">
                                    <div class="fpp-info-box__title"><i class="fas fa-info-circle"></i> What it does</div>
                                    <p>Downloads a complete OS image and updates your current OS. Your media files are preserved, but backing up your configuration is strongly recommended.</p>
                                    <span style="display: block; margin-top: var(--fpp-sp-md); color: #084298;"><strong>Important:</strong> This takes 15-30+ minutes and requires a reboot. <a href="backup.php">Backup first!</a></span>
                                </div>
                            </div>

                            <!-- Warning alert -->
                            <div class="fpp-alert fpp-alert--warning fpp-alert--compact" style="margin-bottom: var(--fpp-sp-lg);">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span><strong>Warning:</strong> OS upgrade will reboot your system. Ensure no shows are running.</span>
                            </div>

                            <!-- Legacy OS warning (shown when checkbox is checked) -->
                            <div id="legacyOSWarning" class="fpp-alert fpp-alert--warning fpp-alert--compact" style="display: none; margin-bottom: var(--fpp-sp-md);">
                                <i class="fas fa-history"></i>
                                <span>Installing a legacy OS is generally not recommended unless you're troubleshooting a specific issue.</span>
                            </div>

                            <div class="card-actions" style="display: flex; align-items: center; gap: var(--fpp-sp-md); flex-wrap: wrap;">
                                <select id="osSelect" class="form-select" onChange="OSSelectChanged();" style="font-family: var(--fpp-font-mono); font-size: var(--fpp-fs-base); padding: var(--fpp-sp-sm) var(--fpp-sp-md); background: #fff; border: 1px solid var(--fpp-border); border-radius: var(--fpp-radius-lg); color: var(--fpp-text-secondary); min-width: 240px; max-width: 100%;">
                                    <option value="">-- Select OS Image --</option>
                                </select>
                                <button class="fpp-btn fpp-btn--warning" id="osUpgradeButton" onclick="UpgradeOS();" disabled>
                                    <i class="fas fa-arrow-up"></i> Upgrade OS
                                </button>
                                <button class="fpp-btn fpp-btn--secondary" id="osDownloadButton" onclick="DownloadOS();" disabled>
                                    <i class="fas fa-cloud-download-alt"></i> Download Only
                                </button>
                                <button class="fpp-btn fpp-btn--outline" id="osReleaseNotesButton" onclick="ViewOSReleaseNotes();" disabled>
                                    <i class="fas fa-file-alt"></i> Release Notes
                                </button>
                            </div>

                            <? if (isset($settings['uiLevel']) && $settings['uiLevel'] >= 1) { ?>
                            <div class="checkbox-options" style="display: flex; flex-wrap: wrap; gap: var(--fpp-sp-lg); margin-top: var(--fpp-sp-md); padding-top: var(--fpp-sp-md); border-top: 1px solid var(--fpp-border-light);">
                                <label class="checkbox-option" style="display: flex; align-items: center; gap: 0.4rem; font-size: var(--fpp-fs-sm); color: var(--fpp-text-muted); cursor: pointer;">
                                    <input type="checkbox" id="allPlatforms" onChange="PopulateOSSelect();" style="accent-color: var(--fpp-info);">
                                    <span class="fpp-badge fpp-badge--info">Adv</span>
                                    Show All Platforms
                                    <img title='Show both BBB & Pi downloads' src='images/redesign/help-icon.svg' class='icon-help'>
                                </label>
                                <label class="checkbox-option" style="display: flex; align-items: center; gap: 0.4rem; font-size: var(--fpp-fs-sm); color: var(--fpp-text-muted); cursor: pointer;">
                                    <input type="checkbox" id="LegacyOS" onChange="PopulateOSSelect();" style="accent-color: var(--fpp-info);">
                                    <span class="fpp-badge fpp-badge--info">Adv</span>
                                    Show Legacy OS
                                    <img title='Include historic OS releases in listing' src='images/redesign/help-icon.svg' class='icon-help'>
                                </label>
                                <? if (isset($settings['uiLevel']) && $settings['uiLevel'] >= 3) { ?>
                                <label class="checkbox-option" style="display: flex; align-items: center; gap: 0.4rem; font-size: var(--fpp-fs-sm); color: var(--fpp-text-muted); cursor: pointer;">
                                    <input type="checkbox" id="keepOptFPP" style="accent-color: #8b5cf6;">
                                    <span class="fpp-badge fpp-badge--dev">Dev</span>
                                    Keep /opt/fpp
                                    <img title='WARNING: This will upgrade the OS but will not upgrade the FPP version running in /opt/fpp. This is useful for developers who are developing the code in /opt/fpp and just want the underlying OS upgraded.' src='images/redesign/help-icon.svg' class='icon-help'>
                                </label>
                                <? } ?>
                            </div>
                            <? } ?>
                        </div>
                    </div>
                </div>



                <? if (isset($settings['uiLevel']) && $settings['uiLevel'] >= 1) { ?>
                    <!-- Advanced Options Card -->
                    <div class="fpp-card fpp-card--accent fpp-card--accent-neutral fpp-card--compact">
                        <div class="fpp-card__header">
                            <div class="fpp-card__icon fpp-card__icon--neutral">
                                <i class="fas fa-code"></i>
                            </div>
                            <div>
                                <h3 class="fpp-card__title">
                                    Advanced Options
                                    <span class="fpp-badge fpp-badge--neutral">Advanced</span>
                                </h3>
                                <p class="fpp-card__subtitle">Tools for developers and power users</p>
                            </div>
                        </div>
                        <p style="margin: 0 0 var(--fpp-sp-md) 0; color: var(--fpp-text-secondary); font-size: var(--fpp-fs-base);">
                            Need to roll back changes? You can revert to a previous git commit using the changelog page.
                            This allows you to undo problematic updates while keeping your configuration.
                        </p>
                        <button class="fpp-btn fpp-btn--secondary" onclick="window.location.href='changelog.php';">
                            <i class="fas fa-history"></i> View Changelog &amp; Revert Options
                        </button>
                    </div>

                    <!-- Version Information -->
                    <div class="fpp-card mt-4">
                        <div class="fpp-card__header-simple">
                            <i class="fas fa-info-circle"></i>
                            <h3>Version Information
                                <span class="fpp-badge fpp-badge--neutral">Advanced</span>
                            </h3>
                        </div>
                        <div class="row">
                            <div class="col-md-4 fpp-col-divider">
                                <div class="fpp-row">
                                    <span class="fpp-row__label">FPP Version:</span>
                                    <span class="fpp-row__value">
                                        <span id="fppVersionStatusBadge" class="fpp-badge fpp-badge--neutral">Checking...</span>
                                        <span id="fppVersionValue"><?= $fppVersion ?></span>
                                    </span>
                                </div>
                                <div class="fpp-row">
                                    <span class="fpp-row__label">Platform:</span>
                                    <span class="fpp-row__value" id="platformValue">
                                        <?php
                                        echo $settings['Platform'];
                                        if (($settings['Variant'] != '') && ($settings['Variant'] != $settings['Platform'])) {
                                            echo " (" . $settings['Variant'] . ")";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 fpp-col-divider">
                                <div class="fpp-row">
                                    <span class="fpp-row__label">OS Build:</span>
                                    <span class="fpp-row__value" id="osReleaseValue">--</span>
                                </div>
                                <div class="fpp-row">
                                    <span class="fpp-row__label">OS Version:</span>
                                    <span class="fpp-row__value">
                                        <span id="osVersionStatusBadge" class="fpp-badge fpp-badge--success" style="display: none;">Up to Date</span>
                                        <span id="osVersionValue">--</span>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <? if (isset($serialNumber) && $serialNumber != "") { ?>
                                    <div class="fpp-row">
                                        <span class="fpp-row__label">Serial Number:</span>
                                        <span class="fpp-row__value"><?= $serialNumber ?></span>
                                    </div>
                                <? } ?>
                                <div class="fpp-row">
                                    <span class="fpp-row__label">Kernel:</span>
                                    <span class="fpp-row__value" id="kernelValue">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <? } ?>

                <!-- FAQ and Resources Row -->
                <div class="row mt-4">
                    <!-- FAQ Section -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                            </div>
                            <div class="card-body">
                                <div class="accordion faq-accordion" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq1Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false">
                                                What's the difference between FPP Software Update and OS Upgrade?
                                            </button>
                                        </h2>
                                        <div id="faq1" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                <b>FPP Software Update</b> updates only the FPP application code (fppd,
                                                web
                                                interface, scripts) from your current git branch. This is quick and
                                                doesn't
                                                require a reboot unless there are system-level changes.
                                                <br><br>
                                                <b>OS Upgrade</b> replaces the entire operating system image, including
                                                the base
                                                OS, kernel, drivers, and FPP. This is more comprehensive but takes
                                                longer and
                                                always requires a reboot.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq2Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false">
                                                Will my settings and sequences be preserved?
                                            </button>
                                        </h2>
                                        <div id="faq2" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes! Both update methods preserve your configuration files, sequences,
                                                playlists, and media files. Your settings are stored in /home/fpp/media
                                                which is
                                                not affected by updates.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq3Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false">
                                                How long does an OS upgrade take?
                                            </button>
                                        </h2>
                                        <div id="faq3" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                OS upgrades typically take 15-30 minutes depending on your internet
                                                connection
                                                speed and SD card/storage speed. The system will download the image,
                                                write it to
                                                storage, and automatically reboot when complete.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq4Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false">
                                                Can I downgrade to an older version?
                                            </button>
                                        </h2>
                                        <div id="faq4" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes, if "Show Legacy OS Versions" is enabled (Advanced UI or higher), you can
                                                select and
                                                install older OS versions. However, this is generally not recommended
                                                unless
                                                you're troubleshooting a specific issue.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq5Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false">
                                                What does "Keep /opt/fpp" do?
                                            </button>
                                        </h2>
                                        <div id="faq5" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                This developer-only option (Developer UI) preserves your /opt/fpp
                                                directory during
                                                an OS upgrade. This allows you to keep a custom-built FPP installation
                                                rather
                                                than using the version included in the OS image. <b>Only use this if
                                                    you're
                                                    actively developing FPP!</b>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Resources -->
                    <div class="col-md-4">
                        <div class="resources-card">
                            <h3><i class="fas fa-link"></i> Resources</h3>
                            <ul>
                                <li><i class="fas fa-code-branch"></i> <a href="https://github.com/FalconChristmas/fpp"
                                        target="_blank">GitHub Repository</a></li>
                                <li><i class="fas fa-book"></i> <a
                                        href="https://github.com/FalconChristmas/fpp/blob/master/README.md"
                                        target="_blank">Documentation</a></li>
                                <li><i class="fas fa-users"></i> <a href="https://www.facebook.com/groups/falconplayer"
                                        target="_blank">Facebook
                                        Group</a></li>
                                <li><i class="fas fa-comments"></i> <a href="http://forums.falconchristmas.com"
                                        target="_blank">Forums</a></li>
                                <li><i class="fas fa-bug"></i> <a href="https://github.com/FalconChristmas/fpp/issues"
                                        target="_blank">Report Issues</a></li>
                                <li><i class="fas fa-heart"></i> <a href="system-stats.php" target="_blank">System
                                        Health</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Comparison Table -->
                <div class="comparison-table card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-balance-scale"></i> Comparison: FPP Update vs OS Upgrade</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="feature-name">Feature</th>
                                    <th style="text-align: center;"><i class="fas fa-sync-alt text-success"></i> FPP
                                        Software Update</th>
                                    <th style="text-align: center;"><i class="fas fa-compact-disc text-warning"></i> OS
                                        Upgrade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="feature-name">Time Required</td>
                                    <td style="text-align: center;">2-5 minutes</td>
                                    <td style="text-align: center;">15-30+ minutes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Reboot Required</td>
                                    <td style="text-align: center;"><i class="fas fa-question text-warning"></i>
                                        Sometimes
                                    </td>
                                    <td style="text-align: center;"><i class="fas fa-check text-warning"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Settings Preserved</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Kept</td>
                                    <td style="text-align: center;"><i
                                            class="fas fa-exclamation-triangle text-warning"></i> Backup Recommended*
                                    </td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Media Files</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Kept</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Kept</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Risk Level</td>
                                    <td style="text-align: center;"><span class="badge bg-success">Low</span></td>
                                    <td style="text-align: center;"><span class="badge bg-warning">Medium</span></td>
                                </tr>
                                <tr>
                                    <td class="feature-name">OS Security Patches</td>
                                    <td style="text-align: center;"><i class="fas fa-times text-danger"></i> No</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Major Version Jump</td>
                                    <td style="text-align: center;"><i class="fas fa-times text-danger"></i> No</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">New Hardware Support</td>
                                    <td style="text-align: center;"><i class="fas fa-times text-danger"></i> No</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Yes</td>
                                </tr>

                            </tbody>
                        </table>
                        <p class="text-muted" style="font-size: 0.85em; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> * OS upgrades preserve settings in most cases, but a
                            backup is always recommended for safety.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'common/footer.inc'; ?>
</body>

</html>