{{/*
Expand the name of the chart.
*/}}
{{- define "cvvfcm.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a fully qualified app name.
*/}}
{{- define "cvvfcm.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Chart name and version label value.
*/}}
{{- define "cvvfcm.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels.
*/}}
{{- define "cvvfcm.labels" -}}
helm.sh/chart: {{ include "cvvfcm.chart" . }}
{{ include "cvvfcm.selectorLabels" . }}
app.kubernetes.io/version: {{ .Values.image.tag | default .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels.
*/}}
{{- define "cvvfcm.selectorLabels" -}}
app.kubernetes.io/name: {{ include "cvvfcm.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Web component selector labels.
*/}}
{{- define "cvvfcm.web.selectorLabels" -}}
{{ include "cvvfcm.selectorLabels" . }}
app.kubernetes.io/component: web
{{- end }}

{{/*
Consumer component selector labels.
*/}}
{{- define "cvvfcm.consumer.selectorLabels" -}}
{{ include "cvvfcm.selectorLabels" . }}
app.kubernetes.io/component: consumer
{{- end }}

{{/*
rtsp-to-web component selector labels.
*/}}
{{- define "cvvfcm.rtsp.selectorLabels" -}}
{{ include "cvvfcm.selectorLabels" . }}
app.kubernetes.io/component: rtsp-to-web
{{- end }}

{{/*
Name of the CloudNativePG cluster. The operator derives from it the "-rw" Service and the
"-app" Secret (keys: uri, host, port, dbname, username, password).
*/}}
{{- define "cvvfcm.database.name" -}}
{{ include "cvvfcm.fullname" . }}-db
{{- end }}

{{/*
Container env for the managed DB: DATABASE_URL is the operator-generated URI plus the query
params Doctrine needs, composed with k8s $(VAR) expansion. DATABASE_URI must precede
DATABASE_URL so the runtime substitution resolves. Empty unless database.enabled.
*/}}
{{- define "cvvfcm.dbEnv" -}}
{{- if .Values.database.enabled }}
- name: DATABASE_URI
  valueFrom:
    secretKeyRef:
      name: {{ include "cvvfcm.database.name" . }}-app
      key: uri
- name: DATABASE_URL
  value: {{ printf "$(DATABASE_URI)?serverVersion=%s&charset=utf8" .Values.externalDatabase.serverVersion | quote }}
{{- end }}
{{- end }}

{{/*
Discrete libpq env for the backup CronJob's pg_dump: the Doctrine DATABASE_URL carries
serverVersion/charset query params that libpq rejects, so pg_dump reads PG* vars instead.
Managed DB only (both deployed envs use it); external DB would supply these explicitly.
*/}}
{{- define "cvvfcm.backupPgEnv" -}}
{{- if .Values.database.enabled }}
{{- range $env, $key := dict "PGHOST" "host" "PGPORT" "port" "PGUSER" "username" "PGDATABASE" "dbname" "PGPASSWORD" "password" }}
- name: {{ $env }}
  valueFrom:
    secretKeyRef:
      name: {{ include "cvvfcm.database.name" $ }}-app
      key: {{ $key }}
{{- end }}
{{- end }}
{{- end }}
