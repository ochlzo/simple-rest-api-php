#!/usr/bin/env python3

import json
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


BASE_URL = "http://localhost:8000"


def ask(prompt, default=""):
    value = input(f"{prompt} ").strip()
    return value or default


def pretty(body):
    if not body:
        return ""

    try:
        data = json.loads(body)
    except json.JSONDecodeError:
        return body.strip()

    return json.dumps(data, indent=2)


def show_error(body, code):
    if not body:
        print(f"HTTP {code}")
        return

    try:
        data = json.loads(body)
    except json.JSONDecodeError:
        print(f"HTTP {code}: {body.strip()}")
        return

    message = data.get("message") or data.get("error")
    print(f"HTTP {code}: {message or body.strip()}")


def call_api(method, path, data=None, query=None):
    url = BASE_URL + path

    if query:
        url += "?" + urlencode(query)

    body = None
    headers = {"Accept": "application/json"}

    if data is not None:
        body = json.dumps(data).encode("utf-8")
        headers["Content-Type"] = "application/json"

    req = Request(url, data=body, headers=headers, method=method)

    try:
        with urlopen(req) as resp:
            raw = resp.read().decode("utf-8")
            text = pretty(raw)

            if text:
                print(text)
            else:
                print(f"HTTP {resp.status}")

    except HTTPError as err:
        raw = err.read().decode("utf-8")
        show_error(raw, err.code)
    except URLError as err:
        print(f"Request failed: {err.reason}")


def signup():
    data = {
        "name": ask("name:"),
        "email": ask("email:"),
        "password": ask("password:"),
    }
    call_api("POST", "/signup", data)


def login():
    data = {
        "email": ask("email:"),
        "password": ask("password:"),
    }
    call_api("POST", "/login", data)


def update():
    data = {
        "email": ask("email:"),
        "new-email": ask("new email:"),
        "new-name": ask("new name:"),
        "new-password": ask("new password:"),
    }
    call_api("PUT", "/update", data)


def delete():
    email = ask("email:")
    call_api("DELETE", "/delete", query={"email": email})


def get_user():
    email = ask("email (blank for all):")
    query = {"email": email} if email else None
    call_api("GET", "/users", query=query)


def root():
    call_api("GET", "/")


def menu():
    print()
    print("1) root")
    print("2) signup")
    print("3) login")
    print("4) users")
    print("5) update")
    print("6) delete")
    print("0) exit")


def main():
    global BASE_URL

    BASE_URL = ask("base url [http://localhost:8000]:", BASE_URL)

    actions = {
        "1": root,
        "2": signup,
        "3": login,
        "4": get_user,
        "5": update,
        "6": delete,
    }

    while True:
        menu()
        choice = ask("choice:")

        if choice == "0":
            break

        action = actions.get(choice)
        if action is None:
            print("invalid choice")
            continue

        action()


if __name__ == "__main__":
    main()
