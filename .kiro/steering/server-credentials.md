---
inclusion: auto
---

# Server Credentials & API Keys

## WHM Server: jp3.broodlepro.com

- **Server ID** (in WHMCS): 2
- **Hostname**: jp3.broodlepro.com
- **IP**: 122.176.149.109
- **WHM Username**: broodle
- **Access Hash (API Token)**: S0MS4V00FKN3PSIOVTEM7SU0Y715GGZJ
- **WHM Port**: 2087
- **Protocol**: https

### API Call Pattern (via PHP curl)

```
https://jp3.broodlepro.com:2087/json-api/cpanel
  ?cpanel_jsonapi_user={cpanelUser}
  &cpanel_jsonapi_apiversion=3
  &cpanel_jsonapi_module={Module}
  &cpanel_jsonapi_func={function}
Header: Authorization: whm broodle:S0MS4V00FKN3PSIOVTEM7SU0Y715GGZJ
```

### Test cPanel Account

- **Username**: eggdeeco
- **Service ID** (in WHMCS): 20
- **Domain**: eggdee.com

## WHMCS Database (Local aapanel)

- **Host**: localhost
- **User**: whmcs
- **Password**: jMiHiXK7L6A5MMeK
- **Database**: whmcs
- **WHMCS Path**: C:\aapanel\BtSoft\wwwroot\whmcs

### MySQL Binary

```
C:\aapanel\BtSoft\mysql\MySQL5.7\bin\mysql.exe -u whmcs -pjMiHiXK7L6A5MMeK whmcs
```

### Quick Query (from bash)

```bash
"/c/aapanel/BtSoft/mysql/MySQL5.7/bin/mysql.exe" -u whmcs -pjMiHiXK7L6A5MMeK whmcs -e "SELECT ..."
```

## PHP Binary

```
C:\aapanel\BtSoft\php\83\php.exe
```

### API Test via PHP (example)

```bash
"/c/aapanel/BtSoft/php/83/php.exe" -r "
\$ch = curl_init();
curl_setopt_array(\$ch, [
    CURLOPT_URL => 'https://jp3.broodlepro.com:2087/json-api/cpanel?cpanel_jsonapi_user=eggdeeco&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=LangPHP&cpanel_jsonapi_func=php_get_installed_versions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ['Authorization: whm broodle:S0MS4V00FKN3PSIOVTEM7SU0Y715GGZJ'],
]);
echo curl_exec(\$ch);
curl_close(\$ch);
"
```

## Confirmed API Response Formats

### LangPHP::php_get_installed_versions
Returns `result.data.versions` (array nested under `versions` key):
```json
{"result":{"data":{"versions":["ea-php80","ea-php81","ea-php82","ea-php83","ea-php84","ea-php85"]}}}
```

### LangPHP::php_get_vhost_versions
Returns `result.data` as array of objects:
```json
{"result":{"data":[{"vhost":"eggdee.com","version":"ea-php83",...}]}}
```

### LangPHP::php_get_system_default_version
Returns `result.data.version`:
```json
{"result":{"data":{"version":"ea-php82"}}}
```

### LangPHP::php_set_vhost_versions
Works with `ea-phpXX` format version strings.
