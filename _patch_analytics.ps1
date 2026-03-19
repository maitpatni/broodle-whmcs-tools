$file = "modules\addons\broodle_whmcs_tools\bt_client.js"
$lines = [System.IO.File]::ReadAllLines($file, [System.Text.Encoding]::UTF8)
$out = New-Object System.Collections.Generic.List[string]

# Track state
$passwordFixed = 0
$analyticsPageAdded = $false
$analyticsSidebarAdded = $false
$inAnalyticsTab = $false
$skipAnalyticsTab = $false

for ($i = 0; $i -lt $lines.Count; $i++) {
    $line = $lines[$i]

    # 1. Fix password font - remove inline style
    if ($line -match 'font-family:inherit;font-weight:500;color:var\(--text-muted' -and $line -match 'Your email account password') {
        $line = $line.Replace(' style="font-family:inherit;font-weight:500;color:var(--text-muted,#6b7280)"', '')
        $passwordFixed++
    }

    # 2. Add analytics page container after addons page
    if ($line -match 'mainArea\.appendChild\(addonsPage\)' -and -not $analyticsPageAdded) {
        $out.Add($line)
        $out.Add('')
        $out.Add('    /* -- Analytics page (hidden by default) -- */')
        $out.Add('    var analyticsPage=document.createElement("div");')
        $out.Add('    analyticsPage.id="bt-analytics-page";')
        $out.Add('    analyticsPage.style.display="none";')
        $out.Add('    mainArea.appendChild(analyticsPage);')
        $analyticsPageAdded = $true
        continue
    }

    # 3. Add analytics sidebar item after WordPress sidebar block
    # Look for the closing } of the wpEnabled block (after the wordpress sidebar item)
    if ($line -match 'data-page="wordpress"' -and -not $analyticsSidebarAdded) {
        $out.Add($line)
        # Find the closing brace
        while ($i + 1 -lt $lines.Count) {
            $i++
            $line = $lines[$i]
            $out.Add($line)
            if ($line.Trim() -eq '}') {
                # Add analytics sidebar item
                $out.Add('    if(C.analyticsEnabled){')
                $out.Add('        html+=''<a class="bt-sidebar-item" data-page="analytics"><div class="bt-si-icon" style="background:rgba(124,58,237,.08);color:#7c3aed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div><div class="bt-si-label">Analytics<span>Usage &amp; Statistics</span></div></a>'';')
                $out.Add('    }')
                $analyticsSidebarAdded = $true
                break
            }
        }
        continue
    }

    # 4. Add analyticsPage to the "Hide all pages" section in bindSidebarActions
    if ($line -match 'var addonsPage=\$\("bt-addons-page"\)' -and $lines[$i-1] -match 'bt-email-page') {
        $out.Add($line)
        # Find the next heroSection line and add analyticsPage hide before it
        while ($i + 1 -lt $lines.Count) {
            $i++
            $line = $lines[$i]
            if ($line -match 'var heroSection=\$\("bt-hero-section"\)') {
                $out.Add('            var analyticsPage=$("bt-analytics-page");')
                $out.Add($line)
                break
            }
            $out.Add($line)
        }
        continue
    }

    # 5. Add analyticsPage hide after addonsPage hide
    if ($line -match 'if\(addonsPage\) addonsPage\.style\.display="none"' -and $lines[$i+1] -match 'if\(page==="tabs"\)') {
        $out.Add($line)
        $out.Add('            if(analyticsPage) analyticsPage.style.display="none";')
        continue
    }

    # 6. Add analytics page handler before changepw handler
    if ($line -match 'else if\(page==="changepw"\)') {
        $out.Add('            }else if(page==="analytics"){')
        $out.Add('                if(heroSection) heroSection.style.display="none";')
        $out.Add('                if(analyticsPage){')
        $out.Add('                    analyticsPage.style.display="";')
        $out.Add('                    if(!analyticsPage.dataset.loaded){')
        $out.Add('                        analyticsPage.dataset.loaded="1";')
        $out.Add('                        buildAnalyticsPageInto(analyticsPage);')
        $out.Add('                        loadAnalytics();')
        $out.Add('                    }')
        $out.Add('                }')
        $out.Add('                if(history.replaceState) history.replaceState(null,null,"#tabAnalytics");')
        $out.Add($line)
        continue
    }

    # 7. Remove analytics from tabs array in buildTabs
    if ($line -match '"analytics".*"Analytics".*analyticsEnabled') {
        # Skip this line (remove analytics tab)
        continue
    }

    # 8. Remove analytics lazy-load from tab click handler
    if ($line -match 'if\(t\.id==="analytics"' -and $line -match 'loadAnalytics') {
        # Skip this line
        continue
    }

    # 9. Remove buildAnalyticsPane call from buildTabs
    if ($line -match 'if\(C\.analyticsEnabled\) buildAnalyticsPane\(\)') {
        # Skip this line
        continue
    }

    # 10. Also hide analyticsPage when a tab is clicked (in the tab click handler)
    if ($line -match 'var emailPage=\$\("bt-email-page"\);if\(emailPage\) emailPage\.style\.display="none"') {
        $out.Add($line)
        $out.Add('            var analyticsPage=$("bt-analytics-page");if(analyticsPage) analyticsPage.style.display="none";')
        continue
    }

    $out.Add($line)
}

# Now convert buildAnalyticsPane to buildAnalyticsPageInto
$content = $out -join "`n"

# Replace the function signature
$content = $content.Replace(
    'function buildAnalyticsPane(){' + "`n" + '    var pane=$("bt-pane-analytics");if(!pane) return;' + "`n" + '    pane.innerHTML=',
    'function buildAnalyticsPageInto(container){' + "`n" + '    if(!container) return;' + "`n" + '    container.innerHTML='
)

# Replace btAnalyticsRefresh binding
$content = $content.Replace(
    '    $("btAnalyticsRefresh").addEventListener("click",function(){loadAnalytics();});' + "`n" + '}',
    '    var refreshBtn=$("btAnalyticsRefresh");if(refreshBtn) refreshBtn.addEventListener("click",function(){loadAnalytics();});' + "`n" + '}'
)

# Add analytics to deep link hash map
$content = $content.Replace(
    '"addons":"addons","addonsextras":"addons","extras":"addons",',
    '"addons":"addons","addonsextras":"addons","extras":"addons",' + "`n" + '        "analytics":"analytics","stats":"analytics","bandwidth":"analytics",'
)

# Add analytics deep link handler before the final tabBtn click
$content = $content.Replace(
    '    var tabBtn=document.querySelector(''.bt-tab-btn[data-tab="''+targetId+''"]'');' + "`n" + '    if(tabBtn) tabBtn.click();',
    '    if(targetId==="analytics"){' + "`n" +
    '        var anItem=document.querySelector(''.bt-sidebar-item[data-page="analytics"]'');' + "`n" +
    '        if(anItem){anItem.click();return;}' + "`n" +
    '    }' + "`n" +
    '    var tabBtn=document.querySelector(''.bt-tab-btn[data-tab="''+targetId+''"]'');' + "`n" +
    '    if(tabBtn) tabBtn.click();'
)

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "Password font fixes: $passwordFixed"
Write-Host "Analytics page container added: $analyticsPageAdded"
Write-Host "Analytics sidebar item added: $analyticsSidebarAdded"
Write-Host "All patches applied successfully"
