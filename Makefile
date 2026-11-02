.PHONY: up down sync metrics

up:
	docker compose up -d --build

down:
	docker compose down

sync:
	docker compose exec app php artisan ojs:sync

metrics: up
	@echo "App metrics:  http://localhost:8000/metrics"
	@echo "Prometheus:   http://localhost:9090/targets"
	@echo "Grafana:      http://localhost:3000 (dashboard: OJS ingestion)"

image:
	docker build -t ojs-editorial-dashboard:latest .

deploy: image
	helm upgrade --install demo deploy/helm/ojs-dashboard \
		--set image.repository=ojs-editorial-dashboard --set image.tag=latest

docker: up
