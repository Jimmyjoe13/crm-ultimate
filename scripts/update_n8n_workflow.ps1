param(
    [string]$Token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkZmVlZWIwMS1jNDkxLTRhMmYtOGIwMC1lOGZiMWMxOGI3MGEiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiYWFmYjIwOWEtNzNhMi00MDVkLWIzOWUtN2U5NmYzZjAyOWNjIiwiaWF0IjoxNzc5NTU3NjYyfQ.8-M-w-gjslzuJZQUy3fmxoUuBCk0E2K28Tq4jXhE6xQ"
)

$N8N_BASE = "https://n8n.nana-intelligence.fr"
$WF_ID    = "q4GXMH5Qzjz9H6AZ"
$API_URL  = "$N8N_BASE/api/v1/workflows/$WF_ID"

$headers = @{
    "X-N8N-API-KEY" = $Token
    "Content-Type"  = "application/json"
    "Accept"        = "application/json"
}

Write-Host "Fetching workflow..."
$wf = Invoke-RestMethod -Uri $API_URL -Headers $headers -Method GET
Write-Host "Got: $($wf.name) -- $($wf.nodes.Count) nodes"

# --- 1. Patch Normalize Event: expose reply_text ---

$normalizeNode = $wf.nodes | Where-Object { $_.name -eq "Normalize Event" }
$normalizeNode.parameters.jsCode = @'
const d = $input.first().json;
const email = d.contact?.email || d.email || '';
const event = d.event || d.type || '';
const date  = d.date  || d.timestamp || d.createdAt || d.created_at || '';

const campaignMap = {
  'conseiller-consultant-acquisition': '69ebb7281762b60a8169f625',
  'acquisition-agence-marketing':      '69eb1cca5033df0a8663a88e',
};
const campaignName = d.campaign || '';
const campaignId   = d.campaign_id || d.campaignId || campaignMap[campaignName] || null;

const eventId = (email && event && date)
  ? (email + '_' + event + '_' + date)
  : null;

return [{ json: {
  event,
  email,
  event_id:      eventId,
  date,
  subject:       d.subject               || null,
  preview:       d.preview || d.previewText || null,
  reply_text:    d.reply || d.replyText || d.fullReply || d.preview || d.previewText || null,
  campaign_id:   campaignId,
  campaign_name: campaignName,
  first_name:    d.contact?.firstName    || null,
  last_name:     d.contact?.lastName     || null,
  step:          d.step                  || null,
} }];
'@

# --- 2. Detect Intent Code node ---
# Regex uses . wildcard for accented French chars to stay ASCII-safe.
# Order: stop > not_interested > out_of_office > interested

$detectCode = @'
const d = $input.first().json;

const event = (d.event || '').toUpperCase();
if (event !== 'REPLIED') return [];

const replyText = (d.reply_text || d.preview || '').trim();
if (!replyText) return [];

const patterns = [
  ['stop', /\b(stop|d.sabonne[rz]?|d.sinscri[sv]?|ne plus me contacter|unsubscribe|remove me|do not contact|opt[- ]?out)\b/i],
  ['not_interested', /\b(pas int.ress.|non merci|ne suis pas int.ress.|aucun int.r.t|pas pour (moi|nous)|not interested|no thanks)\b/i],
  ['out_of_office', /\b(absent|en cong.s?|vacances|retour le|de retour|out of office|ooo|away until|back on|on leave)\b/i],
  ['interested', /\b(int.ress.|oui[,! ]|appel(ez|er)?[- ]moi|rappel(ez|er)?[- ]moi|je souhaite|je veux|dispo(nible)?|call me|interested|yes please|would like|schedule|book)\b/i],
];

let intent = null;
for (const [name, regex] of patterns) {
  if (regex.test(replyText)) { intent = name; break; }
}

if (!intent) return [];

return [{ json: {
  ...d,
  intent,
  reply_text: replyText,
  event_id: (d.event_id ? d.event_id + '@' + intent : null),
}}];
'@

# --- 3. New nodes ---

$detectNode = [ordered]@{
    id          = "c3d4e5f6-a7b8-9012-cdef-234567890123"
    name        = "Detect Intent"
    type        = "n8n-nodes-base.code"
    typeVersion = 2
    position    = @(200, -512)
    parameters  = @{ jsCode = $detectCode }
}

$forwardNode = [ordered]@{
    id               = "d4e5f6a7-b8c9-0123-defa-345678901234"
    name             = "Forward Intent to CRM"
    type             = "n8n-nodes-base.httpRequest"
    typeVersion      = 4.2
    position         = @(420, -512)
    retryOnFail      = $true
    maxTries         = 3
    waitBetweenTries = 5000
    parameters       = [ordered]@{
        method           = "POST"
        url              = "http://crm-app:8080/api/webhooks/emelia-intent"
        sendHeaders      = $true
        headerParameters = @{
            parameters = @(
                @{ name = "Content-Type"; value = "application/json" }
            )
        }
        sendBody       = $true
        contentType    = "raw"
        rawContentType = "application/json"
        body           = '={{ JSON.stringify($json) }}'
        options        = @{}
    }
}

# --- 4. Merge nodes ---

$allNodes = [System.Collections.ArrayList]@($wf.nodes)
$allNodes.Add($detectNode)   | Out-Null
$allNodes.Add($forwardNode)  | Out-Null
Write-Host "Nodes after: $($allNodes.Count)"

# --- 5. Connections ---
# Normalize Event fans out to Forward to CRM (existing) + Detect Intent (new, parallel)

$newConns = [ordered]@{
    "Emelia Trigger1"    = $wf.connections.'Emelia Trigger1'
    "Emelia Trigger"     = $wf.connections.'Emelia Trigger'
    "Normalize Event"    = @{
        main = @(
            ,@(
                @{ node = "Forward to CRM"; type = "main"; index = 0 },
                @{ node = "Detect Intent";  type = "main"; index = 0 }
            )
        )
    }
    "Detect Intent"      = @{
        main = @(
            ,@(
                @{ node = "Forward Intent to CRM"; type = "main"; index = 0 }
            )
        )
    }
    "Schedule Trigger"     = $wf.connections.'Schedule Trigger'
    "get_campaignid"       = $wf.connections.get_campaignid
    "Split Out"            = $wf.connections.'Split Out'
    "get_activities"       = $wf.connections.get_activities
    "Normalize Activities" = $wf.connections.'Normalize Activities'
}

# --- 6. Build body (settings: only executionOrder, strip binaryMode etc.) ---

$putBody = [ordered]@{
    name        = $wf.name
    nodes       = $allNodes.ToArray()
    connections = $newConns
    settings    = @{ executionOrder = "v1" }
    staticData  = $null
}

$bodyJson = $putBody | ConvertTo-Json -Depth 50
Write-Host "Payload: $($bodyJson.Length) chars"

# --- 7. PUT ---

Write-Host "Sending PUT..."
try {
    $result = Invoke-RestMethod -Uri $API_URL -Headers $headers -Method PUT -Body $bodyJson
    Write-Host "SUCCESS - versionId=$($result.versionId) nodes=$($result.nodes.Count)"
} catch {
    Write-Host "FAILED: $($_.Exception.Message)"
    try {
        $stream = $_.Exception.Response.GetResponseStream()
        $rdr    = [System.IO.StreamReader]::new($stream)
        Write-Host "Response: $($rdr.ReadToEnd())"
    } catch {}
}
