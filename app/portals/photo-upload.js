"use client";

import { useState } from "react";

async function optimize(file) {
  if (!file || !file.type.startsWith("image/"))
    throw new Error("Choose an image file.");
  if (file.size > 8 * 1024 * 1024)
    throw new Error("Choose an image smaller than 8 MB.");
  const image = await createImageBitmap(file);
  const scale = Math.min(1, 1600 / Math.max(image.width, image.height));
  const canvas = document.createElement("canvas");
  canvas.width = Math.max(1, Math.round(image.width * scale));
  canvas.height = Math.max(1, Math.round(image.height * scale));
  canvas.getContext("2d").drawImage(image, 0, 0, canvas.width, canvas.height);
  image.close();
  const optimized = await new Promise((resolve) =>
    canvas.toBlob(resolve, "image/webp", 0.82),
  );
  if (!optimized) throw new Error("The image could not be processed.");
  return optimized;
}

export default function PhotoUpload({ db, name, label = "Photo", value = "" }) {
  const [url, setUrl] = useState(value);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  async function upload(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    setBusy(true);
    setMessage("");
    try {
      const image = await optimize(file);
      const path = `${crypto.randomUUID()}.webp`;
      const { error } = await db.storage.from("portal-photos").upload(path, image, {
        contentType: "image/webp",
        cacheControl: "31536000",
        upsert: false,
      });
      if (error) throw error;
      setUrl(db.storage.from("portal-photos").getPublicUrl(path).data.publicUrl);
      setMessage("Photo attached.");
    } catch (error) {
      setMessage(
        error.message?.includes("Bucket not found")
          ? "Apply the photo storage migration in Supabase, then try again."
          : error.message || "The photo could not be uploaded.",
      );
    } finally {
      setBusy(false);
      event.target.value = "";
    }
  }

  return (
    <div className="photo-upload">
      <label>
        {label}
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp,image/avif"
          onChange={upload}
          disabled={busy || !db}
        />
      </label>
      <input type="hidden" name={name} value={url} />
      {url && <img className="photo-upload-preview" src={url} alt="Selected photo preview" />}
      {message && <small role="status">{message}</small>}
    </div>
  );
}
