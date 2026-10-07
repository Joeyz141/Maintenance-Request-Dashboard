# reports/dashboard.py
# The Streamlit dashboard: a second web page for the maintenance team.
#
# Run it (venv on, Apache + MySQL running):
#     python -m streamlit run reports\dashboard.py
# Then open http://localhost:8501
#
# How Streamlit works: it runs this WHOLE script from top to bottom every time
# the user changes something (a filter, a button). Each st.something() call draws
# one piece of the page. No HTML, no JavaScript: Streamlit writes those for us.

from datetime import datetime

import streamlit as st          # "st" is the usual short name
import maintenance_data as md   # our engine: load_requests, summary, charts, ... (same folder)


# --- Page setup (must be the first st. call) ------------------------------------
st.set_page_config(page_title="Maintenance Requests", layout="wide")


# --- Loading the data, with a short cache ---------------------------------------
# @st.cache_data remembers the answer for the same arguments for 10 seconds (ttl).
# Why: Streamlit re-runs the script on EVERY click (even the download button),
# and we don't want to call the API each time. After 10 s the next run asks again.
@st.cache_data(ttl=10)
def get_data(status, priority):
    """Ask the API (with the filters it supports) and return (DataFrame, time loaded)."""
    # None = "All": requests leaves keys with None out of the query string.
    filters = {"status": status, "priority": priority}
    rows = md.load_requests(filters)
    return md.requests_to_dataframe(rows), datetime.now()


# --- Sidebar: the filters ---------------------------------------------------------
st.sidebar.header("Filters")

# selectbox(label, options, format_func, filter_mode): a dropdown.
# options are the database values; format_func decides what the user READS
# ("in_progress" -> "In progress"). None stands for "All".
# filter_mode=None turns typing OFF: the user can only click an option (select-only).
status = st.sidebar.selectbox(
    "Status",
    [None] + md.STATUSES,
    format_func=lambda s: "All" if s is None else md.STATUS_LABELS[s],
    filter_mode=None,
)
priority = st.sidebar.selectbox(
    "Priority",
    [None] + md.PRIORITIES,
    format_func=lambda p: "All" if p is None else p.capitalize(),
    filter_mode=None,
)

# Refresh: forget the cached answer, so this run asks the API again.
if st.sidebar.button("Refresh data"):
    get_data.clear()

auto_refresh = st.sidebar.checkbox("Auto-refresh every 30 s")

st.sidebar.caption(
    "Status and priority are sent to the API (?status=...&priority=...). "
    "Make is filtered here in Python, because the API has no make filter."
)

# Load once here, so the make list below only shows makes that exist in the data.
# If the API fails: a red message for the user, and st.stop() ends this run of the script
# (nothing below is drawn). Errors are NOT cached, so the next run tries again.
try:
    df, loaded_at = get_data(status, priority)
except md.ApiError as error:
    st.error(f"Could not load the maintenance requests. {error}")
    st.stop()

# Make stays a dropdown: the list of makes can grow, and typing helps find one.
# Typing only SEARCHES the list; nobody can enter a make that isn't in it.
make = st.sidebar.selectbox(
    "Make",
    [None] + sorted(df["make"].unique()),
    format_func=lambda m: "All" if m is None else m,
)


# --- Chart toolbar settings -------------------------------------------------------
# Plotly's toolbar (top-right of each chart) has a camera button: "Download plot as a png".
# displayModeBar=True shows the toolbar all the time (not only on hover), so users find it.
# toImageButtonOptions sets the PNG: file name, and scale=2 = twice the pixels (sharper image).
# The PNG is made in the user's browser from what they see, filters included.
def png_config(file_name):
    return {
        "displayModeBar": True,
        "displaylogo": False,   # hide the Plotly logo in the toolbar
        "toImageButtonOptions": {"format": "png", "filename": file_name, "scale": 2},
    }


# --- The main page ------------------------------------------------------------------
# @st.fragment(run_every=...) re-runs ONLY this function every 30 s (the rest of the
# page stays put). run_every=None means "never on its own": auto-refresh is off.
@st.fragment(run_every=30 if auto_refresh else None)
def show_dashboard(status, priority, make):
    # Same protection for the auto-refresh runs: if the API goes down while the page is open,
    # show the message instead of crashing. return = stop this function here.
    try:
        df, loaded_at = get_data(status, priority)
    except md.ApiError as error:
        st.error(f"Could not refresh the maintenance requests. {error}")
        return

    # Make filter in pandas (a boolean mask, like in summary()).
    if make is not None:
        df = df[df["make"] == make]

    st.title("Maintenance Requests")
    st.caption(f"Data from api/requests.php · last refreshed {loaded_at:%I:%M:%S %p}")

    # 1. The 4 number cards
    numbers = md.summary(df)
    c1, c2, c3, c4 = st.columns(4)
    c1.metric("Requests", numbers["total"], help="Requests matching the filters")
    c2.metric("Unfinished", numbers["unfinished"], help="Open + in progress")
    c3.metric("Urgent", numbers["urgent"], help="Unfinished and high priority")
    oldest = numbers["oldest_unfinished_days"]
    c4.metric("Oldest unfinished", "–" if oldest is None else f"{oldest} days")

    # 0 rows is a normal answer (empty list = 200), not an error: say so and stop here.
    if df.empty:
        st.info("No requests match these filters.")
        return

    # 2. The two charts, side by side (the functions from Step 4)
    left, right = st.columns(2)
    left.plotly_chart(md.status_priority_chart(df), width="stretch", config=png_config("requests_by_status_priority"))
    right.plotly_chart(md.make_chart(df), width="stretch", config=png_config("requests_per_make"))
    st.caption("To save a chart as an image: click the camera icon at its top-right corner.")

    # 3. The table
    st.subheader("Requests")
    table = df[["id", "title", "make", "model", "model_year", "status", "priority", "created_at", "days_open"]].copy()
    table["status"] = table["status"].map(md.STATUS_LABELS)
    st.dataframe(
        table,
        hide_index=True,
        width="stretch",
        column_config={
            "id": st.column_config.NumberColumn("#", format="%d"),
            "title": "Title",
            "make": "Make",
            "model": "Model",
            "model_year": st.column_config.NumberColumn("Year", format="%d"),
            "status": "Status",
            "priority": "Priority",
            "created_at": st.column_config.DatetimeColumn("Created", format="YYYY-MM-DD HH:mm"),
            "days_open": st.column_config.NumberColumn("Days open", format="%d"),
        },
    )

    # 4. Download CSV: exactly the rows on screen (with the filters applied).
    #    to_csv() turns the table into CSV text; .encode() turns the text into bytes for the file.
    st.download_button(
        "Download CSV",
        data=df.to_csv(index=False).encode("utf-8"),
        file_name="maintenance_requests.csv",
        mime="text/csv",
    )


show_dashboard(status, priority, make)
