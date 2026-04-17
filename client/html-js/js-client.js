function pretty(body) {
  if (!body) {
    return "";
  }

  try {
    return JSON.stringify(JSON.parse(body), null, 2);
  } catch {
    return body.trim();
  }
}

function buildUrl(baseUrl, path, query) {
  const url = new URL(path, baseUrl);

  if (query) {
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        url.searchParams.set(key, value);
      }
    });
  }

  return url.toString();
}

async function callApi(method, path, data = null, query = null) {
  const baseUrl = document.getElementById("baseUrl").value.trim() || "http://localhost:8080";
  const url = buildUrl(baseUrl, path, query);

  const headers = {
    Accept: "application/json",
  };

  const options = {
    method,
    headers,
  };

  if (data !== null) {
    headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(data);
  }

  const response = await fetch(url, options);
  const raw = await response.text();
  const text = pretty(raw);

  if (!response.ok) {
    let message = `HTTP ${response.status}`;

    try {
      const parsed = JSON.parse(raw);
      message = parsed.message || parsed.error || message;
    } catch {
      if (raw && raw.trim()) {
        message = raw.trim();
      }
    }

    const error = new Error(message);
    error.status = response.status;
    error.body = text || raw;
    throw error;
  }

  return {
    status: response.status,
    body: text || `HTTP ${response.status}`,
  };
}

function setStatus(message) {
  document.getElementById("statusLabel").textContent = message;
}

function setResponse(message) {
  document.getElementById("responseBox").textContent = message;
}

function setButtonsDisabled(disabled) {
  document.querySelectorAll("button").forEach((button) => {
    if (!button.classList.contains("tab-button")) {
      button.disabled = disabled;
    }
  });
}

async function runRequest(handler) {
  setStatus("Sending request...");
  setButtonsDisabled(true);

  try {
    const response = await handler();
    setResponse(response.body);
    setStatus("Success");
  } catch (error) {
    setResponse(error.body || error.message || "Request failed.");
    setStatus("Error");
  } finally {
    setButtonsDisabled(false);
  }
}

function setupTabs() {
  const tabButtons = document.querySelectorAll(".tab-button");
  const panels = document.querySelectorAll(".panel");

  tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const tab = button.dataset.tab;

      tabButtons.forEach((item) => item.classList.remove("active"));
      panels.forEach((panel) => panel.classList.remove("active"));

      button.classList.add("active");
      document.getElementById(tab).classList.add("active");
    });
  });
}

function setupActions() {
  document.getElementById("rootBtn").addEventListener("click", () => {
    runRequest(() => callApi("GET", "/"));
  });

  document.getElementById("signupBtn").addEventListener("click", () => {
    const data = {
      name: document.getElementById("signupName").value.trim(),
      email: document.getElementById("signupEmail").value.trim(),
      password: document.getElementById("signupPassword").value,
    };

    runRequest(() => callApi("POST", "/signup", data));
  });

  document.getElementById("loginBtn").addEventListener("click", () => {
    const data = {
      email: document.getElementById("loginEmail").value.trim(),
      password: document.getElementById("loginPassword").value,
    };

    runRequest(() => callApi("POST", "/login", data));
  });

  document.getElementById("usersBtn").addEventListener("click", () => {
    const email = document.getElementById("usersEmail").value.trim();
    const query = email ? { email } : null;

    runRequest(() => callApi("GET", "/users", null, query));
  });

  document.getElementById("updateBtn").addEventListener("click", () => {
    const data = {
      email: document.getElementById("updateEmail").value.trim(),
      "new-email": document.getElementById("updateNewEmail").value.trim(),
      "new-name": document.getElementById("updateNewName").value.trim(),
      "new-password": document.getElementById("updateNewPassword").value,
    };

    runRequest(() => callApi("PUT", "/update", data));
  });

  document.getElementById("deleteBtn").addEventListener("click", () => {
    const email = document.getElementById("deleteEmail").value.trim();

    runRequest(() => callApi("DELETE", "/delete", null, { email }));
  });
}

document.addEventListener("DOMContentLoaded", () => {
  setupTabs();
  setupActions();
  setStatus("Ready");
});
