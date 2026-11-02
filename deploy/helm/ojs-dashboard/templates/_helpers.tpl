{{- define "ojs-dashboard.name" -}}
ojs-dashboard
{{- end }}

{{- define "ojs-dashboard.fullname" -}}
{{- printf "%s-%s" .Release.Name (include "ojs-dashboard.name" .) | trunc 63 | trimSuffix "-" }}
{{- end }}

{{- define "ojs-dashboard.labels" -}}
app.kubernetes.io/name: {{ include "ojs-dashboard.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{- define "ojs-dashboard.selectorLabels" -}}
app.kubernetes.io/name: {{ include "ojs-dashboard.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{- define "ojs-dashboard.dbHost" -}}
{{- if .Values.mysql.enabled -}}
{{ include "ojs-dashboard.fullname" . }}-mysql
{{- else -}}
{{ required "db.host is required when mysql.enabled=false" .Values.db.host }}
{{- end }}
{{- end }}

{{- define "ojs-dashboard.env" -}}
envFrom:
  - configMapRef:
      name: {{ include "ojs-dashboard.fullname" . }}-config
  - secretRef:
      name: {{ include "ojs-dashboard.fullname" . }}-secrets
{{- end }}

{{- define "ojs-dashboard.writableVolumes" -}}
volumes:
  - name: storage
    emptyDir: {}
  - name: bootstrap-cache
    emptyDir: {}
  - name: run
    emptyDir: {}
  - name: tmp
    emptyDir: {}
{{- end }}

{{- define "ojs-dashboard.volumeMounts" -}}
volumeMounts:
  - name: storage
    mountPath: /app/storage
  - name: bootstrap-cache
    mountPath: /app/bootstrap/cache
  - name: run
    mountPath: /run
  - name: tmp
    mountPath: /tmp
{{- end }}

{{- define "ojs-dashboard.podSecurityContext" -}}
securityContext:
  runAsNonRoot: true
  runAsUser: 10001
  fsGroup: 10001
  seccompProfile:
    type: RuntimeDefault
{{- end }}

{{- define "ojs-dashboard.containerSecurityContext" -}}
securityContext:
  runAsNonRoot: true
  runAsUser: 10001
  allowPrivilegeEscalation: false
  readOnlyRootFilesystem: true
  capabilities:
    drop: ["ALL"]
{{- end }}
