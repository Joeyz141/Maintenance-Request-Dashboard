# tests/python/test_maintenance_data.py
# Unit tests for reports/maintenance_data.py.
#
# Run them from the project folder (venv on):
#     python -m pytest
# pytest finds this file because its name starts with "test_" (and pytest.ini points here),
# runs every function whose name starts with "test_", and reports each one as passed or failed.
#
# A test = 3 parts (often called Arrange / Act / Assert):
#   1. Arrange: build a small, known input by hand
#   2. Act:     call ONE function from maintenance_data.py
#   3. Assert:  "assert <something that must be True>"; if it's False, pytest shows what went wrong
#
# None of these tests need Apache, MySQL or the internet: we build the data ourselves,
# and for load_requests() we swap the real HTTP call for a fake one (see the end of the file).

import re
from pathlib import Path

import pandas as pd
import pytest
import requests

import maintenance_data as md   # pytest.ini adds reports/ to the import path, so this works


# --- Helpers: small, known test data ------------------------------------------------
# A "factory": builds one request dict, with sensible defaults we can override.
# make_row(status="completed") -> the same row, but completed.
def make_row(**changes):
    row = {
        "id": 1,
        "title": "Brake noise",
        "priority": "high",
        "status": "open",
        "created_at": "2026-10-01 09:00:00",
        "vin": "TESTVIN0000000001",
        "make": "Ford",
        "model": "F-150",
        "model_year": 2022,
    }
    row.update(changes)   # replace the defaults with whatever the test passed in
    return row


# Turns a list of rows into the same DataFrame the dashboard uses.
def make_df(rows):
    return md.requests_to_dataframe(rows)


# --- requests_to_dataframe() --------------------------------------------------------

def test_dataframe_has_one_row_per_request_and_real_dates():
    df = make_df([make_row(id=1), make_row(id=2)])

    assert len(df) == 2
    # created_at must be a real date now, not text (so we can do date math on it)
    assert pd.api.types.is_datetime64_any_dtype(df["created_at"])


def test_days_open_counts_whole_days():
    # Created 3 days and 2 hours ago -> 3 whole days (the 2 hours are dropped)
    created = pd.Timestamp.now() - pd.Timedelta(days=3, hours=2)
    df = make_df([make_row(created_at=created.strftime("%Y-%m-%d %H:%M:%S"))])

    assert df.loc[0, "days_open"] == 3


def test_empty_list_still_has_all_columns():
    # 0 rows is a normal answer (no request matched the filters) and must not crash
    df = make_df([])

    assert len(df) == 0
    for column in md.COLUMNS + ["days_open"]:
        assert column in df.columns


# --- count_by() ---------------------------------------------------------------------

def test_count_by_uses_our_order_and_shows_zero():
    df = make_df([make_row(status="open"), make_row(status="open"), make_row(status="completed")])

    counts = md.count_by(df, "status", md.STATUSES)

    # .tolist() turns the pandas result into a plain list, easy to compare
    assert counts.index.tolist() == ["open", "in_progress", "completed"]   # our fixed order
    assert counts.tolist() == [2, 0, 1]                                    # in_progress: 0, not missing


# --- status_priority_table() --------------------------------------------------------

def test_status_priority_table_is_always_3_by_3():
    # Only ONE (status, priority) pair in the data...
    df = make_df([make_row(status="open", priority="low")])

    table = md.status_priority_table(df)

    # ...but every status row and every priority column is still there
    assert table.shape == (3, 3)
    assert table.index.tolist() == md.STATUSES
    assert table.columns.tolist() == md.PRIORITIES
    assert table.loc["open", "low"] == 1
    assert table.values.sum() == 1   # every other cell is 0


# --- requests_per_make() ------------------------------------------------------------

def test_requests_per_make_sorted_most_first():
    df = make_df([make_row(make="Ford"), make_row(make="Toyota"), make_row(make="Toyota")])

    per_make = md.requests_per_make(df)

    assert per_make.index.tolist() == ["Toyota", "Ford"]
    assert per_make.tolist() == [2, 1]


# --- summary() ----------------------------------------------------------------------

def test_summary_counts_unfinished_and_urgent():
    df = make_df([
        make_row(id=1, status="open",        priority="high"),     # unfinished + urgent
        make_row(id=2, status="in_progress", priority="high"),     # unfinished + urgent
        make_row(id=3, status="in_progress", priority="low"),      # unfinished only
        make_row(id=4, status="completed",   priority="high"),     # finished: NOT urgent
    ])

    result = md.summary(df)

    assert result["total"] == 4
    assert result["unfinished"] == 3
    assert result["urgent"] == 2


def test_summary_oldest_unfinished_ignores_completed():
    old = (pd.Timestamp.now() - pd.Timedelta(days=30)).strftime("%Y-%m-%d %H:%M:%S")
    newer = (pd.Timestamp.now() - pd.Timedelta(days=5, hours=1)).strftime("%Y-%m-%d %H:%M:%S")
    df = make_df([
        make_row(status="completed", created_at=old),    # oldest overall, but finished
        make_row(status="open", created_at=newer),
    ])

    assert md.summary(df)["oldest_unfinished_days"] == 5


def test_summary_with_nothing_unfinished_gives_none():
    # max() of an empty column would be NaN; summary() must give None instead
    df = make_df([make_row(status="completed")])

    assert md.summary(df)["oldest_unfinished_days"] is None


def test_summary_of_empty_dataframe():
    result = md.summary(make_df([]))

    assert result == {"total": 0, "unfinished": 0, "urgent": 0, "oldest_unfinished_days": None}


def test_summary_unknown_status_is_not_counted_as_unfinished():
    # The open question from Ticket 9: isin(UNFINISHED) vs  != "completed".
    # Today the database only allows open / in_progress / completed, so both give the same answer.
    # They differ the day a new status (e.g. "cancelled") is added:
    #   != "completed"      -> would count "cancelled" as unfinished (wrong)
    #   isin(UNFINISHED)    -> only counts what we LISTED as unfinished (what we want)
    df = make_df([make_row(status="cancelled"), make_row(status="open")])

    assert md.summary(df)["unfinished"] == 1


# --- The lists must match the database ----------------------------------------------
# A "contract test": schema.sql is the single source of truth for the allowed values.
# If someone adds a status to the database but forgets this file, this test fails.

def enum_values(column):
    """Read the ENUM values of one column straight from database/schema.sql."""
    schema = (Path(__file__).resolve().parents[2] / "database" / "schema.sql").read_text(encoding="utf-8")
    # Finds e.g.   status      ENUM('open', 'in_progress', 'completed')
    match = re.search(column + r"\s+ENUM\(([^)]*)\)", schema)
    assert match, f"No ENUM found for {column} in schema.sql"
    return re.findall(r"'([^']*)'", match.group(1))


def test_statuses_match_schema():
    assert sorted(md.STATUSES) == sorted(enum_values("status"))


def test_priorities_match_schema():
    assert sorted(md.PRIORITIES) == sorted(enum_values("priority"))


def test_every_status_has_a_label_and_every_priority_a_color():
    assert set(md.STATUS_LABELS) == set(md.STATUSES)
    assert set(md.PRIORITY_COLORS) == set(md.PRIORITIES)


# --- load_requests(): the error handling, with a FAKE HTTP call ----------------------
# We don't want these tests to need Apache. pytest's "monkeypatch" temporarily replaces
# requests.get with our own function for ONE test, then puts the real one back.

class FakeResponse:
    """Pretends to be the object requests.get() returns."""

    def __init__(self, status_code, json_data=None, is_json=True):
        self.status_code = status_code
        self.ok = 200 <= status_code < 300      # like the real response.ok
        self._json_data = json_data
        self._is_json = is_json

    def json(self):
        if not self._is_json:
            raise ValueError("not JSON")        # what the real .json() does with an HTML page
        return self._json_data


def fake_get_returning(response):
    """Build a replacement for requests.get that always gives back this response."""
    def fake_get(url, params=None, timeout=None):
        return response
    return fake_get


def test_load_requests_returns_the_data_list(monkeypatch):
    rows = [make_row(id=1), make_row(id=2)]
    monkeypatch.setattr(md.requests, "get", fake_get_returning(FakeResponse(200, {"data": rows, "count": 2})))

    assert md.load_requests() == rows


def test_load_requests_500_shows_the_api_message(monkeypatch):
    body = {"error": "The maintenance requests could not be loaded right now."}
    monkeypatch.setattr(md.requests, "get", fake_get_returning(FakeResponse(500, body)))

    # pytest.raises = "this code MUST raise this error"; match= checks the message too
    with pytest.raises(md.ApiError, match="status 500. The maintenance requests could not be loaded"):
        md.load_requests()


def test_load_requests_html_404_page(monkeypatch):
    # Wrong URL: Apache answers with its own HTML 404 page, which isn't JSON
    monkeypatch.setattr(md.requests, "get", fake_get_returning(FakeResponse(404, is_json=False)))

    with pytest.raises(md.ApiError, match="status 404"):
        md.load_requests()


def test_load_requests_200_but_not_json(monkeypatch):
    monkeypatch.setattr(md.requests, "get", fake_get_returning(FakeResponse(200, is_json=False)))

    with pytest.raises(md.ApiError, match="did not answer with JSON"):
        md.load_requests()


def test_load_requests_json_without_data(monkeypatch):
    monkeypatch.setattr(md.requests, "get", fake_get_returning(FakeResponse(200, {"rows": []})))

    with pytest.raises(md.ApiError, match="without a 'data' list"):
        md.load_requests()


def test_load_requests_apache_off(monkeypatch):
    def fake_get(url, params=None, timeout=None):
        raise requests.exceptions.ConnectionError("connection refused")
    monkeypatch.setattr(md.requests, "get", fake_get)

    with pytest.raises(md.ApiError, match="Is Apache running"):
        md.load_requests()


def test_load_requests_timeout(monkeypatch):
    def fake_get(url, params=None, timeout=None):
        raise requests.exceptions.Timeout("too slow")
    monkeypatch.setattr(md.requests, "get", fake_get)

    with pytest.raises(md.ApiError, match="longer than 10 seconds"):
        md.load_requests()


def test_load_requests_sends_filters_and_a_timeout(monkeypatch):
    # "Spy" version of the fake: it remembers what it was called with
    calls = []

    def fake_get(url, params=None, timeout=None):
        calls.append({"url": url, "params": params, "timeout": timeout})
        return FakeResponse(200, {"data": []})
    monkeypatch.setattr(md.requests, "get", fake_get)

    md.load_requests({"status": "open"})

    assert calls[0]["url"] == md.API_URL
    assert calls[0]["params"] == {"status": "open"}
    assert calls[0]["timeout"] == 10   # never wait forever
