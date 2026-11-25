"use client";

import { useEffect, useState } from "react";
import { adminAccountRequest } from "./admin-client";
import { CardPreview, defaultCard } from "./id-card";

const numeric = {
  width: ["Card width (mm)", 48, 186],
  height: ["Card height (mm)", 28, 140],
  photoTop: ["Photo top (%)", 0, 85],
  photoLeft: ["Photo left (%)", 0, 85],
  photoSize: ["Photo size (px)", 16, 180],
  photoRadius: ["Photo corner radius (%)", 0, 50],
  nameTop: ["Name top (%)", 0, 94],
  nameLeft: ["Name left (%)", 0, 90],
  nameSize: ["Name size (px)", 7, 36],
  detailsTop: ["Details top (%)", 0, 94],
  detailsLeft: ["Details left (%)", 0, 90],
  detailsSize: ["Details size (px)", 7, 30],
};

export default function CardDesigner({ db }) {
  const [design, setDesign] = useState(defaultCard);
  const [students, setStudents] = useState([]);
  const [chosen, setChosen] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const example = students.find((item) => item.id === chosen) ||
    students[0] || {
      display_name: "Sample Student",
      member_number: "NUS–0247",
    };

  useEffect(() => {
    if (!db) return;
    db.rpc("portal_card_design").then(({ data, error }) => {
      if (error) setNotice(error.message);
      else if (data) setDesign({ ...defaultCard, ...data });
    });
    adminAccountRequest(db, "GET")
      .then(({ accounts }) => {
        setStudents(
          accounts.filter(
            (account) => account.role === "student" && account.active !== false,
          ),
        );
      })
      .catch((error) => setNotice(error.message));
  }, [db]);

  function set(key, value) {
    setDesign((current) => ({ ...current, [key]: value }));
  }

  async function upload(file) {
    if (!file) return;
    if (
      !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
      file.size > 8_000_000
    ) {
      setNotice("Choose a JPG, PNG or WebP file under 8 MB.");
      return;
    }
    try {
      const image = await createImageBitmap(file);
      const scale = Math.min(1, 1100 / Math.max(image.width, image.height));
      const canvas = document.createElement("canvas");
      canvas.width = Math.max(1, Math.round(image.width * scale));
      canvas.height = Math.max(1, Math.round(image.height * scale));
      canvas
        .getContext("2d")
        .drawImage(image, 0, 0, canvas.width, canvas.height);
      image.close();
      const background = canvas.toDataURL("image/webp", 0.82);
      if (background.length > 500000)
        throw new Error("Choose a smaller background image.");
      set("background", background);
      setNotice("");
    } catch (error) {
      setNotice(error.message || "The background image could not be loaded.");
    }
  }

  async function run(action) {
    setBusy(true);
    setNotice("");
    try {
      await action();
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="panel site-content">
      <h2>ID card designer</h2>
      <p role="status">{notice}</p>
      {students.length > 0 && (
        <label>
          Preview student
          <select
            value={chosen}
            onChange={(event) => setChosen(event.target.value)}
          >
            <option value="">First student</option>
            {students.map((student) => (
              <option key={student.id} value={student.id}>
                {student.display_name}
              </option>
            ))}
          </select>
        </label>
      )}
      <CardPreview profile={example} design={design} />
      <form
        onSubmit={(event) => {
          event.preventDefault();
          run(async () => {
            const { error } = await db.rpc("admin_set_option", {
              option_name: "card_design",
              value: design,
            });
            if (error) throw error;
            setNotice("ID card design saved.");
          });
        }}
      >
        <label>
          Background image
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(event) => upload(event.target.files[0])}
          />
        </label>
        {design.background && (
          <button
            type="button"
            className="secondary"
            onClick={() => set("background", "")}
          >
            Remove background
          </button>
        )}
        <details className="admin-disclosure">
          <summary>Position, size and colors</summary>
          <div className="admin-card-fields">
            {Object.entries(numeric).map(([key, [label, min, max]]) => (
              <label key={key}>
                {label}
                <input
                  type="range"
                  min={min}
                  max={max}
                  value={design[key]}
                  onChange={(event) => set(key, Number(event.target.value))}
                />
                <output>{design[key]}</output>
              </label>
            ))}
            <label>
              Name color
              <input
                type="color"
                value={design.nameColor}
                onChange={(event) => set("nameColor", event.target.value)}
              />
            </label>
            <label>
              Details color
              <input
                type="color"
                value={design.detailsColor}
                onChange={(event) => set("detailsColor", event.target.value)}
              />
            </label>
          </div>
        </details>
        <div className="dialog-actions">
          <button className="primary" disabled={busy || !db}>
            Save design
          </button>
          <button
            type="button"
            className="secondary"
            disabled={busy || !students.length}
            onClick={() =>
              run(async () => {
                const { downloadCards } = await import("./admin-pdf");
                const missing = await downloadCards(design, students);
                setNotice(
                  "Student ID card PDF downloaded." +
                    (missing
                      ? " " +
                        missing +
                        " photos did not allow PDF export and use initials."
                      : ""),
                );
              })
            }
          >
            Download student ID cards
          </button>
        </div>
      </form>
    </section>
  );
}
