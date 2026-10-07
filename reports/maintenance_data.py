# reports/maintenance_data.py
# Shared functions for the Python report and the Streamlit dashboard.

# Step 1: load_requests() asks api/requests.php for the data
# Step 2: requests_to_dataframe() turns those rows into a pandas table (DataFrame)
# Step 3: analysis functions: counts, status x priority, per make, summary numbers
# Step 4: chart functions: they RETURN Plotly figures; the dashboard decides where to show them
# Step 6: error handling: every way the API call can fail becomes ONE error type, ApiError

import os         # os.getenv(): read environment variables
import requests   # the HTTP library: our Python version of fetch()
import pandas as pd   # tables (DataFrames); "pd" is the short name everyone uses
import plotly.express as px   # Plotly's quick chart functions; "px" is the usual short name

# Where the API lives.
# os.getenv("API_URL", default) reads the environment variable API_URL;
# if it isn't set, it uses the localhost address (the same idea as getenv('DB_PASS') in PHP).
API_URL = os.getenv("API_URL", "http://localhost/maintenance-dashboard/api/requests.php")

# The columns the API sends for each request
# A list in square brackets. We use it so an EMPTY result still has the right columns.
COLUMNS = ["id", "title", "priority", "status", "created_at", "vin", "make", "model", "model_year"]

# The allowed values, in the order we want to SHOW them (same lists as src/validation.php).
# Fixed order = every chart and table lists them the same way, even when a count is 0.
STATUSES = ["open", "in_progress", "completed"]
PRIORITIES = ["high", "medium", "low"]
UNFINISHED = ["open", "in_progress"]   # "still needs work" = everything except completed

# Labels people read on the charts (the database values stay as they are).
STATUS_LABELS = {"open": "Open", "in_progress": "In progress", "completed": "Completed"}

# One fixed color per priority, so "high" is the same orange on every chart.
# (Colorblind-checked: orange / blue / green stay distinguishable.)
PRIORITY_COLORS = {"high": "#eb6834", "medium": "#2a78d6", "low": "#1baf7a"}

# --- Step 6. Our own error type -----------------------------------------------------------
# "class ApiError(Exception)" = a new kind of error, built on Python's basic Exception.
# The dashboard only has to catch ONE thing (ApiError) instead of 5 different library errors,
# and every ApiError carries a message written for people, not for programmers.
class ApiError(Exception):
    """The maintenance requests could not be loaded from the API."""


# --- Step 1. Convert JSON text into Python dictionary ---------------------------------------------------------
def load_requests(filters=None):
    """Return the maintenance requests from the API as a list of dictionaries.

    filters: optional dict, e.g. {"status": "open", "priority": "high"}.
             None (the default) means "no filters, get every request".
    Raises ApiError (with a message for people) if the data can't be loaded.
    """
    # 1. Call the API.
    #    params=filters -> requests builds the query string for us:
    #                      {"status": "open"} becomes ?status=open  (None = no query string)
    #    timeout=10     -> give up after 10 seconds instead of waiting forever
    #    try/except: these two failures happen BEFORE any answer arrives.
    #    "raise ... from error" keeps the original error attached, for debugging.
    try:
        response = requests.get(API_URL, params=filters, timeout=10)
    except requests.exceptions.Timeout as error:
        raise ApiError("The API took longer than 10 seconds to answer. Try again in a moment.") from error
    except requests.exceptions.ConnectionError as error:
        raise ApiError(f"Could not reach the API at {API_URL}. Is Apache running?") from error

    # 2. Check the status code. Like fetch(), requests does NOT fail on 404 or 500 by itself.
    #    response.ok is True for 2xx (like `response.ok` in JS). Instead of raise_for_status()
    #    we check it ourselves, so we can show the API's own short message (e.g. a 500's "error").
    if not response.ok:
        try:
            reason = response.json().get("error", "")   # our API sends {"error": "..."}
        except ValueError:
            reason = ""                                 # not JSON (e.g. Apache's HTML 404 page)
        raise ApiError(f"The API answered with status {response.status_code}. {reason}".strip())

    # 3. Turn the JSON text into Python: JSON object -> dict, JSON array -> list.
    #    ValueError = the answer wasn't JSON at all (wrong URL pointing at an HTML page, PHP warning...).
    try:
        payload = response.json()
    except ValueError as error:
        raise ApiError("The API did not answer with JSON. Check that API_URL points to api/requests.php.") from error

    # 4. The API answers with an "envelope": {"data": [...], "count": 10, "filters": {...}}.
    #    We only need the rows. Check the envelope really has them before using it.
    if not isinstance(payload, dict) or "data" not in payload:
        raise ApiError("The API answered, but without a 'data' list.")
    return payload["data"]

# --- Step 2. Dictionaries --> Dataframe ---------------------------------------------------------
def requests_to_dataframe(rows):
    """Turn the list of request dicts into a pandas DataFrame with real dates and a days_open column."""
    # 1. List of dicts -> table. Each dict becomes a row, each key becomes a column.
    #    columns=COLUMNS: if rows is empty ([] = no request matched the filters),
    df = pd.DataFrame(rows, columns=COLUMNS)

    # 2. created_at arrives as TEXT ('2026-10-05 10:48:49'), because JSON has no date type.
    #    pd.to_datetime() converts the whole column into real dates.
    df["created_at"] = pd.to_datetime(df["created_at"])

    # 3. New column: how many whole days since the request was created.
    #    pd.Timestamp.now()          -> right now, as a pandas date
    #    now - df["created_at"]      -> a "timedelta" (a length of time) for every row at once
    #    .dt.days                    -> keep only the whole days (2 days 5 hours -> 2)
    df["days_open"] = (pd.Timestamp.now() - df["created_at"]).dt.days

    return df


# --- Step 3. analysis ---------------------------------------------------------
# Each function takes the DataFrame and answers ONE question.
# Small functions = easy to test, and report.py AND dashboard.py can both reuse them.

def count_by(df, column, order):
    """How many requests per value of one column in a fixed order."""
    # value_counts()            -> counts each value
    # .reindex(order, ...)      -> put the values in OUR order (open, in_progress, completed)
    # fill_value=0              -> a value with no rows shows 0 instead of disappearing
    return df[column].value_counts().reindex(order, fill_value=0)


def status_priority_table(df):
    """A table: one row per status, one column per priority, each cell = number of requests."""
    # pd.crosstab(rows, columns) counts every (status, priority) pair,
    #  laid out as a grid.
    table = pd.crosstab(df["status"], df["priority"])
    return table.reindex(index=STATUSES, columns=PRIORITIES, fill_value=0)


def requests_per_make(df):
    """How many requests per vehicle make, most requests first."""
    return df["make"].value_counts()   # value_counts() already sorts from most to least


def summary(df):
    """The headline numbers for the dashboard cards, as a dict."""
    # A "boolean mask": one True/False per row. True = the status is in UNFINISHED.
    # .isin() is pandas' version of PHP's in_array().
    is_unfinished = df["status"].isin(UNFINISHED)

    # df[mask] keeps only the rows where the mask is True (like a WHERE clause).
    unfinished = df[is_unfinished]

    # Two conditions at once: & means AND. Each condition needs its own ( ).
    urgent = df[is_unfinished & (df["priority"] == "high")]

    return {
        "total": len(df),
        "unfinished": len(unfinished),
        "urgent": len(urgent),
        # max() of an empty column is NaN (not a number), so check first.
        # "X if condition else Y" is Python's one-line if/else (like ?: in PHP and JS).
        "oldest_unfinished_days": int(unfinished["days_open"].max()) if len(unfinished) > 0 else None,
    }


# --- Step 4. charts -----------------------------------------------------------
# These functions BUILD a chart and RETURN it (a Plotly "figure" object).
# They don't show anything themselves: the dashboard shows it with st.plotly_chart(fig),
# and the test below shows it with fig.show().

def status_priority_chart(df):
    """Stacked bar chart: one bar per status, split into colors by priority."""
    # 1. Start from the grid we already have (rows = status, columns = priority).
    table = status_priority_table(df)

    # 2. Plotly wants "long" data: one row per (status, priority, count),
    #    not a grid. melt() unpivots the grid:
    #       status       high medium low            status       priority  requests
    #       open            0      1   0     ->     open         high             0
    #       ...                                     open         medium           1 ...
    #    reset_index() first turns the status labels (the index) back into a normal column.
    long = table.reset_index().melt(id_vars="status", var_name="priority", value_name="requests")

    # 3. Nicer labels for people: "in_progress" -> "In progress".
    #    .map(dict) replaces each value using the dictionary.
    long["status"] = long["status"].map(STATUS_LABELS)

    # 4. Build the chart.
    fig = px.bar(
        long,
        x="status",
        y="requests",
        color="priority",                          # one color per priority = the stacked parts
        color_discrete_map=PRIORITY_COLORS,        # our fixed colors
        category_orders={                          # our fixed order (not alphabetical)
            "status": [STATUS_LABELS[s] for s in STATUSES],
            "priority": PRIORITIES,
        },
        labels={"status": "Status", "requests": "Requests", "priority": "Priority"},
        title="Requests by status and priority",
    )
    # 5. Small layout touches: whole numbers only on the y-axis (no "2.5 requests").
    fig.update_layout(barmode="stack", yaxis_tickformat="d")
    return fig


def make_chart(df):
    """Horizontal bar chart: number of requests per vehicle make."""
    # value_counts() gives a Series; reset_index() turns it into a 2-column table: make, count
    counts = requests_per_make(df).reset_index()

    fig = px.bar(
        counts,
        x="count",
        y="make",
        orientation="h",                           # horizontal bars: long names stay readable
        labels={"count": "Requests", "make": "Make"},
        title="Requests per vehicle make",
    )
    # Biggest bar at the top (Plotly draws the first category at the bottom by default).
    fig.update_layout(yaxis={"categoryorder": "total ascending"}, xaxis_tickformat="d")
    return fig


# This block runs ONLY when you run this file directly:
#python reports\maintenance_data.py
# When another file imports it (report.py, dashboard.py), __name__ is "maintenance_data",
# not "__main__", so this quick test is skipped.
if __name__ == "__main__":
    try:
        rows = load_requests()
    except ApiError as error:
        print("Error:", error)
        raise SystemExit(1)   # stop here; exit code 1 tells the terminal "this failed"
    print(len(rows), "requests loaded")
    print(rows[0])   # the first row: a dict with id, title, priority, status, ...

    # Step 2 test: the same rows as a table
    df = requests_to_dataframe(rows)
    print()
    print(df[["id", "title", "status", "priority", "created_at", "days_open"]])  # pick 6 columns
    print()
    print(df.dtypes)   # the type of each column: created_at should now be datetime64

    # Step 3 test: the analysis
    print()
    print("Summary:", summary(df))
    print()
    print("By status:")
    print(count_by(df, "status", STATUSES))
    print()
    print("By priority:")
    print(count_by(df, "priority", PRIORITIES))
    print()
    print("Status x priority:")
    print(status_priority_table(df))
    print()
    print("Per make:")
    print(requests_per_make(df))

    # Step 4 test: each fig.show() opens the chart in a new browser tab.
    status_priority_chart(df).show()
    make_chart(df).show()
