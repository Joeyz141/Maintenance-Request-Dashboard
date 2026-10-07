# deploy/analytics/Dockerfile
# The Python + Streamlit container (Ticket 9 dashboard).

# pandas 3 needs Python 3.11+. "slim" = smaller Debian image.
FROM python:3.12-slim

# Don't run the app as root inside the container.
RUN useradd --create-home app
WORKDIR /app

# Install libraries first (cached by Docker unless requirements.txt changes).
COPY reports/requirements.txt reports/requirements.txt
RUN pip install --no-cache-dir -r reports/requirements.txt

COPY reports/    reports/
COPY .streamlit/ .streamlit/

USER app

# API_URL: maintenance_data.py reads it with os.getenv(). Inside the Docker network
# the PHP container is reachable by its service name "web" (compose.yaml).
ENV API_URL=http://web/api/requests.php

EXPOSE 8501

# --server.baseUrlPath=analytics: the dashboard lives at https://<site>/analytics/
# --server.address=0.0.0.0: listen on the container's network, not only on its own localhost.
CMD ["python", "-m", "streamlit", "run", "reports/dashboard.py", \
     "--server.address=0.0.0.0", \
     "--server.baseUrlPath=analytics", \
     "--server.headless=true", \
     "--browser.gatherUsageStats=false"]
