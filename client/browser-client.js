const output = document.getElementById('output');
const baseUrlInput = document.getElementById('baseUrl');
const storageKey = 'simple-rest-api-php-base-url';

function setOutput(value) {
  output.textContent = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
}

function loadBaseUrl() {
  const saved = localStorage.getItem(storageKey);

  if (saved) {
    baseUrlInput.value = saved;
  }
}

function saveBaseUrl() {
  localStorage.setItem(storageKey, baseUrlInput.value.trim() || 'http://localhost:8000');
  setOutput(`Base URL saved: ${baseUrlInput.value.trim() || 'http://localhost:8000'}`);
}

function normalizeBaseUrl() {
  return (baseUrlInput.value.trim() || 'http://localhost:8000').replace(/\/+$/, '');
}

function buildUrl(path, query = {}) {
  const url = new URL(normalizeBaseUrl() + path);

  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && String(value).trim() !== '') {
      url.searchParams.set(key, String(value).trim());
    }
  }

  return url.toString();
}

async function callApi(method, path, { query, body } = {}) {
  const options = {
    method,
    headers: {
      Accept: 'application/json',
    },
  };

  if (body !== undefined) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(buildUrl(path, query), options);
    const text = await response.text();

    let parsed = text;
    try {
      parsed = text ? JSON.parse(text) : null;
    } catch (_) {
      // Leave the raw text in place when the body is not JSON.
    }

    setOutput({
      status: response.status,
      ok: response.ok,
      body: parsed,
    });
  } catch (error) {
    setOutput({
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

document.getElementById('saveBaseUrl').addEventListener('click', saveBaseUrl);

document.getElementById('rootButton').addEventListener('click', () => {
  callApi('GET', '/');
});

document.getElementById('signupButton').addEventListener('click', () => {
  callApi('POST', '/signup', {
    body: {
      name: document.getElementById('signupName').value,
      email: document.getElementById('signupEmail').value,
      password: document.getElementById('signupPassword').value,
    },
  });
});

document.getElementById('loginButton').addEventListener('click', () => {
  callApi('POST', '/login', {
    body: {
      email: document.getElementById('loginEmail').value,
      password: document.getElementById('loginPassword').value,
    },
  });
});

document.getElementById('usersButton').addEventListener('click', () => {
  callApi('GET', '/users', {
    query: {
      email: document.getElementById('usersEmail').value,
    },
  });
});

document.getElementById('updateButton').addEventListener('click', () => {
  callApi('PUT', '/update', {
    body: {
      email: document.getElementById('updateEmail').value,
      'new-email': document.getElementById('updateNewEmail').value,
      'new-name': document.getElementById('updateNewName').value,
      'new-password': document.getElementById('updateNewPassword').value,
    },
  });
});

document.getElementById('deleteButton').addEventListener('click', () => {
  callApi('DELETE', '/delete', {
    query: {
      email: document.getElementById('deleteEmail').value,
    },
  });
});

loadBaseUrl();
